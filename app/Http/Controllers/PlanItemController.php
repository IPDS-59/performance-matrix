<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\LinkPlansToProjectsAction;
use App\Http\Controllers\Concerns\ResolvesTeams;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Notifications\KinetikNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Members plan their own work; the PJ plans for any member of the team.
 * No approval step: the PJ sees every plan and can edit or cancel it.
 */
class PlanItemController extends Controller
{
    use ResolvesTeams;

    public function __construct(private readonly LinkPlansToProjectsAction $links) {}

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate($this->rules() + ['team_id' => ['required', 'integer', 'exists:teams,id']]);
        $team = Team::findOrFail($data['team_id']);
        $employeeId = (int) ($data['employee_id'] ?? $actor->id);

        $this->authorizeFor($actor, $team, $employeeId);
        $this->checkRk($team, $employeeId, $data);

        $item = PlanItem::create([
            ...collect($data)->except(['employee_id', 'team_id'])->all(),
            'team_id' => $team->id,
            'employee_id' => $employeeId,
            'source' => $employeeId === $actor->id ? 'member' : 'pj',
            'created_by' => $actor->id,
        ]);

        $this->notifyOwner($request, $actor, $item, 'ditambahkan');

        return back()->with('success', 'Rencana disimpan.');
    }

    public function update(Request $request, PlanItem $planItem): RedirectResponse
    {
        $actor = $this->actor($request);
        $team = Team::findOrFail($planItem->team_id);
        $this->authorizeFor($actor, $team, $planItem->employee_id);
        abort_unless($planItem->status === 'planned', 422, 'Rencana yang sudah dikirim ke kipApp tidak dapat diubah di sini.');

        $data = $request->validate($this->rules());
        $this->checkRk($team, $planItem->employee_id, $data);
        $planItem->update(collect($data)->except('employee_id')->all());

        $this->notifyOwner($request, $actor, $planItem, 'diubah');

        return back()->with('success', 'Rencana diperbarui.');
    }

    /** Cancel instead of delete, so the Friday evaluation can still see it. */
    public function destroy(Request $request, PlanItem $planItem): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeFor($actor, Team::findOrFail($planItem->team_id), $planItem->employee_id);
        abort_unless($planItem->status === 'planned', 422, 'Rencana yang sudah dikirim ke kipApp tidak dapat dibatalkan di sini.');

        $planItem->update(['status' => 'cancelled']);
        $this->notifyOwner($request, $actor, $planItem, 'dibatalkan');

        return back()->with('success', 'Rencana dibatalkan.');
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'description' => ['required', 'string', 'max:1000'],
            'date_start' => ['required', 'date'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'target' => ['nullable', 'numeric', 'gt:0'],
            'target_unit' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function actor(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        return $employee;
    }

    /** A member plans for themselves, the PJ for anyone in the team. */
    private function authorizeFor(Employee $actor, Team $team, int $employeeId): void
    {
        abort_unless($team->members()->where('employees.id', $employeeId)->exists(), 422, 'Pegawai ini bukan anggota tim.');
        abort_unless($employeeId === $actor->id || $this->isPj($actor, $team->id), 403, 'Anda hanya dapat merencanakan pekerjaan Anda sendiri.');
    }

    /** The RK must belong to the team, and a Projek is required unless the RK has none (same rule as claims). */
    private function checkRk(Team $team, int $employeeId, array $data): void
    {
        $plan = PerformancePlan::with('project')->findOrFail($data['performance_plan_id']);
        if (($plan->project?->team_id ?? $plan->team_id) !== $team->id) {
            throw ValidationException::withMessages(['performance_plan_id' => 'RK ini bukan milik tim yang dipilih.']);
        }
        if ($plan->project_id === null && empty($data['project_id']) && ! $this->links->projectOptional($plan)) {
            throw ValidationException::withMessages(['project_id' => 'Pilih projek untuk RK ini.']);
        }
    }

    private function notifyOwner(Request $request, Employee $actor, PlanItem $item, string $verb): void
    {
        $owner = Employee::find($item->employee_id)?->user;
        if ($owner === null || $owner->id === $request->user()->id) {
            return;
        }

        $owner->notify(new KinetikNotification(
            'plan_item',
            ($actor->display_name ?? $actor->name)." {$verb} rencana untuk Anda: ".Str::limit($item->description, 120),
            route('weekly-plan.index', ['team' => $item->team_id, 'week' => Carbon::parse($item->date_start)->toDateString()]),
        ));
    }
}
