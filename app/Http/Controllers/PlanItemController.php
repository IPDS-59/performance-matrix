<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\CompletePlanItemAction;
use App\Actions\Kinetik\DeletePlanItemAction;
use App\Actions\Kinetik\LinkPlansToProjectsAction;
use App\Actions\Kinetik\ListPresensiAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Actions\Kinetik\SendPlanItemAction;
use App\Actions\Kinetik\UpdatePlanItemAction;
use App\Http\Controllers\Concerns\ResolvesTeams;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Notifications\KinetikNotification;
use App\Services\Kinetik\PlanEvaluator;
use App\Services\Kinetik\PlanItemAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
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

    public function __construct(private readonly LinkPlansToProjectsAction $links, private readonly PlanItemAccess $access) {}

    public function store(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $data = $request->validate($this->rules($request) + ['team_id' => ['required', 'integer', 'exists:teams,id']], $this->messages());
        $team = Team::findOrFail($data['team_id']);
        $employeeId = (int) ($data['employee_id'] ?? $actor->id);

        $this->authorizeFor($actor, $team, $employeeId);
        $this->checkRk($team, $employeeId, $data);
        $this->checkQuarter($data);
        if (! empty($data['parent_id']) && ! PlanItem::whereKey($data['parent_id'])->where('employee_id', $employeeId)->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'Kegiatan asal salinan tidak ditemukan.']);
        }

        $item = PlanItem::create([
            ...$this->attributes($data, ['employee_id', 'team_id']),
            'team_id' => $team->id,
            'employee_id' => $employeeId,
            'source' => $employeeId === $actor->id ? 'member' : 'pj',
            'created_by' => $actor->id,
        ]);

        $this->notifyOwner($request, $actor, $item, 'ditambahkan');

        return back()->with('success', 'Rencana disimpan.');
    }

    public function update(Request $request, PlanItem $planItem, UpdatePlanItemAction $kipUpdate): RedirectResponse
    {
        $actor = $this->actor($request);
        $team = Team::findOrFail($planItem->team_id);
        $this->authorizeFor($actor, $team, $planItem->employee_id);

        if ($planItem->kip_external_id === null && $planItem->status === 'planned') {
            $data = $request->validate($this->rules($request), $this->messages());
            $this->checkRk($team, $planItem->employee_id, $data);
            $this->checkQuarter($data);
            $planItem->update($this->attributes($data, ['employee_id', 'parent_id']));
        } else {
            // Already in kipApp (KIP_EDIT_AFTER_PUSH): kipApp is written first, Kinetik saves after it accepts.
            abort_unless($this->access->canEdit($planItem), 422, $this->access->lockReason($planItem) ?? 'Rencana yang sudah dikirim ke kipApp tidak dapat diubah di sini.');
            $planItem->loadMissing('employee.user');

            if ($planItem->kip_sent_at !== null) {
                // Like kipApp: a sent kegiatan only changes "Masukan ke capaian SKP".
                $attributes = ['is_capaian_skp' => $request->validate(['is_capaian_skp' => ['required', 'boolean']])['is_capaian_skp']];
            } else {
                $data = $request->validate($this->rules($request), $this->messages());
                $data['performance_plan_id'] = $planItem->performance_plan_id;
                $this->checkRk($team, $planItem->employee_id, $data);
                $this->checkQuarter($data);
                if (Carbon::parse($data['date_start'])->quarter !== $this->access->quarterOf($planItem)) {
                    throw ValidationException::withMessages(['date_start' => 'Kegiatan yang sudah ada di kipApp harus tetap di triwulan yang sama.']);
                }
                $attributes = $this->attributes($data, ['employee_id', 'parent_id', 'performance_plan_id', 'team_id']);
            }

            try {
                $kipUpdate->execute($planItem, $attributes);
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        $this->notifyOwner($request, $actor, $planItem, 'diubah');

        return back()->with('success', 'Rencana diperbarui.');
    }

    /** Send the plan to kipApp now instead of waiting for its start date. */
    public function push(Request $request, PlanItem $planItem, PushPlanItemAction $push): RedirectResponse
    {
        $this->authorizeFor($this->actor($request), Team::findOrFail($planItem->team_id), $planItem->employee_id);
        abort_unless($planItem->status === 'planned', 422, 'Rencana ini sudah dikirim ke kipApp.');
        if (! $this->access->hasStarted($planItem)) {
            return back()->with('error', PlanItemAccess::NOT_STARTED);
        }

        return $push->execute($planItem->load(['employee.user', 'performancePlan']))
            ? back()->with('success', 'Rencana dikirim ke kipApp.')
            : back()->with('error', 'Belum terkirim: '.$planItem->fresh()->push_error);
    }

    /** The member (or PJ) marks the plan complete: progres 100 goes to kipApp. */
    public function complete(Request $request, PlanItem $planItem, CompletePlanItemAction $complete): RedirectResponse
    {
        $this->authorizeFor($this->actor($request), Team::findOrFail($planItem->team_id), $planItem->employee_id);
        abort_if($planItem->status === 'cancelled', 422, 'Rencana ini sudah dibatalkan.');

        $data = $request->validate($this->resultRules());
        if ($planItem->kip_external_id === null && ! $this->access->hasStarted($planItem)) {
            return back()->with('error', PlanItemAccess::NOT_STARTED);
        }
        // kipApp wants a capaian text of 10 characters; Kinetik asks for it when the capaian goes to kipApp.
        $note = trim((string) ($data['capaian_note'] ?? $planItem->capaian_note));
        if ($note === '' || mb_strlen($note) < 10) {
            throw ValidationException::withMessages(['capaian_note' => $note === '' ? 'Masukan Capaian' : 'Capaian minimal 10 karakter.']);
        }

        try {
            $complete->execute($planItem->load(['employee.user', 'performancePlan']), $data['capaian_note'] ?? null, $data['evidence_url'] ?? null, isset($data['capaian']) ? (float) $data['capaian'] : null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Rencana ditandai selesai dan dikirim ke kipApp.');
    }

    /**
     * Saves the result of a plan in Kinetik only (capaian, uraian, bukti, progres).
     * Nothing is written to kipApp here; "Tandai selesai" sends it.
     */
    public function result(Request $request, PlanItem $planItem): RedirectResponse
    {
        $this->authorizeFor($this->actor($request), Team::findOrFail($planItem->team_id), $planItem->employee_id);
        abort_if($planItem->status === 'cancelled', 422, 'Rencana ini sudah dibatalkan.');

        $planItem->update($this->attributes($request->validate($this->resultRules())));

        return back()->with('success', 'Capaian disimpan.');
    }

    /** "Kirim ke atasan": the kegiatan in kipApp is sent for the atasan to rate. */
    public function send(Request $request, PlanItem $planItem, SendPlanItemAction $send): RedirectResponse
    {
        return $this->sendOrCancel($request, new EloquentCollection([$planItem]), fn ($plans) => $send->send($plans), 'Kegiatan dikirim ke atasan.');
    }

    /** "Batalkan kirim": takes the kegiatan back so it can be changed. */
    public function unsend(Request $request, PlanItem $planItem, SendPlanItemAction $send): RedirectResponse
    {
        return $this->sendOrCancel($request, new EloquentCollection([$planItem]), fn ($plans) => $send->cancel($plans), 'Kegiatan berhasil dikembalikan.');
    }

    /** Bulk "Kirim ke atasan (n)": one kipApp call for the selected kegiatan of a member. */
    public function sendMany(Request $request, SendPlanItemAction $send): RedirectResponse
    {
        return $this->sendOrCancel($request, $this->selected($request), fn ($plans) => $send->send($plans), 'Kegiatan dikirim ke atasan.');
    }

    /** Bulk "Batalkan kirim (n)". */
    public function unsendMany(Request $request, SendPlanItemAction $send): RedirectResponse
    {
        return $this->sendOrCancel($request, $this->selected($request), fn ($plans) => $send->cancel($plans), 'Kegiatan berhasil dikembalikan.');
    }

    /** @return EloquentCollection<int, PlanItem> */
    private function selected(Request $request): EloquentCollection
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer', 'exists:plan_items,id']], ['ids.required' => 'Tidak ada kegiatan yang dipilih', 'ids.min' => 'Tidak ada kegiatan yang dipilih'])['ids'];

        return PlanItem::whereKey($ids)->get();
    }

    /** @param  EloquentCollection<int, PlanItem>  $plans */
    private function sendOrCancel(Request $request, EloquentCollection $plans, \Closure $run, string $done): RedirectResponse
    {
        abort_unless(config('kinetik.kip.send_to_atasan'), 403, 'Kirim ke atasan belum diaktifkan.');
        $actor = $this->actor($request);
        $plans->each(fn (PlanItem $p) => $this->authorizeFor($actor, Team::findOrFail($p->team_id), $p->employee_id));
        $plans->load('employee.user');

        try {
            $run($plans);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $done);
    }

    /** The member's presensi for the quarter of a date, for the "List Aktivitas" picker. */
    public function presensi(Request $request, ListPresensiAction $presensi): JsonResponse
    {
        $date = Carbon::parse($request->validate(['date' => ['required', 'date']])['date']);

        try {
            return response()->json(['data' => $presensi->execute($this->actor($request), $date)]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
    public function destroy(Request $request, PlanItem $planItem, DeletePlanItemAction $kipDelete): RedirectResponse
    {
        $actor = $this->actor($request);
        $this->authorizeFor($actor, Team::findOrFail($planItem->team_id), $planItem->employee_id);

        if ($planItem->kip_external_id === null && $planItem->status === 'planned') {
            $planItem->update(['status' => 'cancelled']);
        } else {
            // Already in kipApp (KIP_EDIT_AFTER_PUSH): the kegiatan is deleted there first.
            abort_unless($this->access->canDelete($planItem), 422, $this->access->lockReason($planItem) ?? 'Rencana yang sudah dikirim ke kipApp tidak dapat dibatalkan di sini.');
            try {
                $kipDelete->execute($planItem->load('employee.user'));
            } catch (\RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }
        }
        $this->notifyOwner($request, $actor, $planItem, 'dibatalkan');

        return back()->with('success', 'Rencana dibatalkan.');
    }

    /** @return array<string, array<int, mixed>> */
    private function resultRules(): array
    {
        return [
            'capaian' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'capaian_note' => ['nullable', 'string', 'max:1000'],
            'evidence_url' => ['nullable', 'url', 'max:2000'],
            'progress' => ['nullable', 'integer', 'between:0,100'],
        ];
    }

    /**
     * The validated fields as model attributes. Progres is a non-null column, so
     * an empty value is left out instead of overwriting it with null.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    private function attributes(array $data, array $except = []): array
    {
        return collect($data)->except($except)->reject(fn ($value, $key) => in_array($key, ['progress', 'is_capaian_skp'], true) && $value === null)->all();
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(Request $request): array
    {
        return $this->resultRules() + [
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            // TrimStrings makes a text of spaces null, so "required" also covers "only spaces".
            'description' => ['required', 'string', 'min:10', 'max:1000'],
            'date_start' => ['required', 'date'],
            // "Gunakan periode tanggal" and "Gunakan jam" are independent: a range may have times.
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'time_start' => ['nullable', 'date_format:H:i'],
            'time_end' => ['nullable', 'date_format:H:i', 'required_with:time_start', 'after:time_start'],
            'target' => ['nullable', 'numeric', 'gt:0'],
            'target_unit' => ['nullable', 'string', 'max:100'],
            'is_capaian_skp' => ['nullable', 'boolean'],
            'parent_id' => ['nullable', 'integer', 'exists:plan_items,id'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'description.required' => 'Masukan Kegiatan. Kegiatan tidak boleh hanya spasi.',
            'description.min' => 'Kegiatan minimal 10 karakter.',
        ];
    }

    /** One SKP is one quarter of the year in kinetik.kip.tahun, so both dates must fall in it. */
    private function checkQuarter(array $data): void
    {
        $start = Carbon::parse($data['date_start']);
        $end = Carbon::parse($data['date_end']);
        $tahun = (int) config('kinetik.kip.tahun');

        if ($start->year !== $tahun || $end->year !== $tahun) {
            throw ValidationException::withMessages(['date_start' => "Tanggal harus berada di tahun {$tahun}."]);
        }
        if ($start->quarter !== $end->quarter) {
            throw ValidationException::withMessages(['date_end' => 'Tanggal mulai dan selesai harus berada dalam satu triwulan (satu SKP).']);
        }
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
            route('weekly.index', ['week' => Carbon::parse($item->date_start)->toDateString()]),
        ));
    }
}
