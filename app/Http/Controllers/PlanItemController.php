<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\CompletePlanItemAction;
use App\Actions\Kinetik\LinkPlansToProjectsAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Http\Controllers\Concerns\ResolvesTeams;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Notifications\KinetikNotification;
use App\Services\Kinetik\PlanEvaluator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    /** Send the plan to kipApp now instead of waiting for its start date. */
    public function push(Request $request, PlanItem $planItem, PushPlanItemAction $push): RedirectResponse
    {
        $this->authorizeFor($this->actor($request), Team::findOrFail($planItem->team_id), $planItem->employee_id);
        abort_unless($planItem->status === 'planned', 422, 'Rencana ini sudah dikirim ke kipApp.');

        return $push->execute($planItem->load(['employee.user', 'performancePlan']))
            ? back()->with('success', 'Rencana dikirim ke kipApp.')
            : back()->with('error', 'Belum terkirim: '.$planItem->fresh()->push_error);
    }

    /** The member (or PJ) marks the plan complete: progres 100 goes to kipApp. */
    public function complete(Request $request, PlanItem $planItem, CompletePlanItemAction $complete): RedirectResponse
    {
        $this->authorizeFor($this->actor($request), Team::findOrFail($planItem->team_id), $planItem->employee_id);
        abort_if($planItem->status === 'cancelled', 422, 'Rencana ini sudah dibatalkan.');

        $data = $request->validate([
            'capaian' => ['nullable', 'string', 'max:1000'],
            'evidence_url' => ['nullable', 'url', 'max:2000'],
        ]);

        try {
            $complete->execute($planItem->load(['employee.user', 'performancePlan']), $data['capaian'] ?? null, $data['evidence_url'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rencana ditandai selesai dan dikirim ke kipApp.');
    }

    /**
     * Friday evaluation: the PJ corrects the result kipApp shows, with a
     * reason. An empty status removes the correction.
     */
    public function evaluate(Request $request, PlanItem $planItem): RedirectResponse
    {
        $actor = $this->actor($request);
        abort_unless($this->isPj($actor, $planItem->team_id), 403, 'Hanya PJ yang dapat mengoreksi hasil rencana.');

        $data = $request->validate([
            'status' => ['nullable', Rule::in(PlanEvaluator::STATES)],
            'reason' => ['required_with:status', 'nullable', 'string', 'max:500'],
        ]);

        if (empty($data['status'])) {
            $planItem->update(['override_status' => null, 'override_reason' => null, 'override_by' => null]);

            return back()->with('success', 'Koreksi PJ dihapus.');
        }

        $planItem->update(['override_status' => $data['status'], 'override_reason' => trim($data['reason']), 'override_by' => $actor->id]);
        $this->notifyOwner($request, $actor, $planItem, 'dikoreksi hasilnya');

        return back()->with('success', 'Koreksi disimpan.');
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
