<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\SaveActivityClaimAction;
use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\RecapLock;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class WeeklyActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;

        $weekParam = $request->query('week');

        // Default to the week of the employee's most recent activity (so there is
        // always something to claim), falling back to the current week.
        $latestActivity = $employee
            ? KipActivity::where('employee_id', $employee->id)->max('activity_date_start')
            : null;

        $anchor = match (true) {
            $weekParam !== null => Carbon::parse($weekParam),
            $latestActivity !== null => Carbon::parse($latestActivity),
            default => now(),
        };
        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = $anchor->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $rawActivities = $employee
            ? KipActivity::with('claim')
                ->where('employee_id', $employee->id)
                ->whereBetween('activity_date_start', [$weekStart, $weekEnd])
                ->orderBy('activity_date_start')
                ->get()
            : collect();

        // Resolve rk_external_id → local performance_plan_id so the frontend
        // can auto-select the correct RK without requiring the user to pick manually.
        $rkIds = $rawActivities->pluck('rk_external_id')->filter()->unique();
        $planByRkId = $rkIds->isNotEmpty()
            ? PerformancePlan::whereIn('kip_external_id', $rkIds)->pluck('id', 'kip_external_id')
            : collect();

        // Team of each matched RK, to tell the member up front when the PJ locked the period.
        $planIds = $planByRkId->values()->merge($rawActivities->pluck('claim.performance_plan_id'))->filter()->unique();
        $teamByPlanId = PerformancePlan::with('project:id,team_id')
            ->whereIn('id', $planIds)
            ->get()
            ->mapWithKeys(fn (PerformancePlan $plan) => [$plan->id => $plan->project?->team_id ?? $plan->team_id]);

        $activities = $rawActivities->map(function (KipActivity $a) use ($planByRkId, $teamByPlanId) {
            $planId = $a->claim?->performance_plan_id
                ?? ($a->rk_external_id ? $planByRkId->get($a->rk_external_id) : null);
            $teamId = $planId ? $teamByPlanId->get($planId) : null;

            return array_merge($a->toArray(), [
                'matched_plan_id' => $a->rk_external_id ? $planByRkId->get($a->rk_external_id) : null,
                'locked' => $teamId !== null && RecapLock::coversDate($teamId, Carbon::parse($a->activity_date_start)),
            ]);
        });

        $recap = $employee
            ? ActivityClaim::with(['performancePlan.project', 'project', 'kipActivity'])
                ->where('employee_id', $employee->id)
                ->whereDate('week_start', $weekStart)
                ->where('status', 'saved')
                ->get()
            : collect();

        $plans = collect();
        $projects = collect();

        if ($employee) {
            $teamIds = $employee->teams()->pluck('teams.id');

            $plans = PerformancePlan::with('project.team', 'team')
                ->where(function ($q) use ($teamIds) {
                    $q->whereIn('team_id', $teamIds)
                        ->orWhereHas('project', fn ($p) => $p->whereIn('team_id', $teamIds));
                })
                ->get()
                ->map(fn (PerformancePlan $plan) => [
                    'id' => $plan->id,
                    'description' => $plan->description,
                    'project_id' => $plan->project_id,
                    'project_name' => $plan->project?->name ?? null,
                    'team_id' => $plan->project?->team_id ?? $plan->team_id,
                    'team_name' => $plan->project?->team?->name ?? $plan->team?->name ?? '—',
                ]);

            // Projek choices for team-scoped RKs; the member's own projects first.
            $ownProjectIds = $employee->projects()->pluck('projects.id');
            $projects = Project::whereIn('team_id', $teamIds)
                ->orderBy('name')
                ->get(['id', 'name', 'team_id'])
                ->map(fn (Project $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'team_id' => $p->team_id,
                    'is_member' => $ownProjectIds->contains($p->id),
                ])
                ->sortByDesc('is_member')
                ->values();
        }

        $prevWeek = Carbon::parse($weekStart)->subWeek()->toDateString();
        $nextWeek = Carbon::parse($weekStart)->addWeek()->toDateString();

        // PJ (team leader) fills Solusi + RTL; members only fill Kendala.
        $isPj = $employee !== null && $this->isPj($employee);

        return Inertia::render('Kinetik/WeeklyScrapper', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'display_name' => $employee->display_name,
            ] : null,
            'activities' => $activities,
            'recap' => $recap,
            'plans' => $plans,
            'projects' => $projects,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'isPj' => $isPj,
        ]);
    }

    /**
     * Save several claims at once. All or nothing: one invalid or locked claim
     * rolls the whole batch back so the member never ends up half-saved.
     */
    public function storeClaimsBulk(Request $request, SaveActivityClaimAction $action): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'claims' => ['required', 'array', 'min:1', 'max:100'],
            ...collect(self::claimRules())->mapWithKeys(fn ($rules, $field) => ["claims.*.{$field}" => $rules])->all(),
        ], [], [
            'claims.*.obstacle' => 'Kendala',
            'claims.*.performance_plan_id' => 'Rencana Kinerja',
        ]);

        $isPj = $this->isPj($employee);

        try {
            DB::transaction(function () use ($validated, $isPj, $action, $employee) {
                foreach ($validated['claims'] as $claim) {
                    $claim['status'] = $claim['status'] ?? 'saved';
                    if (! $isPj) {
                        unset($claim['solution'], $claim['follow_up_plan']);
                    }
                    $action->execute($employee, $claim);
                }
            });
        } catch (AuthorizationException $e) {
            return back()->with('error', 'Tidak diizinkan: '.$e->getMessage());
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first() ?? 'Sebagian kegiatan gagal disimpan.');
        }

        $count = count($validated['claims']);

        return back()->with('success', "{$count} kegiatan berhasil disimpan ke rekap mingguan.");
    }

    /**
     * @return array<string, array<int, string>>
     */
    private static function claimRules(): array
    {
        return [
            'kip_activity_id' => ['nullable', 'integer', 'exists:kip_activities,id'],
            'performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'work_item_id' => ['nullable', 'integer', 'exists:work_items,id'],
            'target' => ['nullable', 'numeric', 'min:0'],
            'realization' => ['nullable', 'numeric', 'min:0'],
            'target_unit' => ['nullable', 'string', 'max:100'],
            'obstacle' => ['required', 'string'],
            'solution' => ['nullable', 'string'],
            'follow_up_plan' => ['nullable', 'string'],
            'activity_date_start' => ['required', 'date'],
            'activity_date_end' => ['nullable', 'date'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'evidence_url' => ['nullable', 'url', 'max:2048'],
            'status' => ['sometimes', 'in:draft,saved'],
        ];
    }

    private function isPj(Employee $employee): bool
    {
        return Team::where('leader_id', $employee->id)->exists()
            || $employee->teams()->wherePivot('role', 'leader')->exists();
    }

    public function storeClaim(Request $request, SaveActivityClaimAction $action): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate(self::claimRules());

        $validated['status'] = $validated['status'] ?? 'saved';

        // Solusi & Rencana Tindak Lanjut are PJ-only (filled at the team recap).
        if (! $this->isPj($employee)) {
            unset($validated['solution'], $validated['follow_up_plan']);
        }

        try {
            $action->execute($employee, $validated);
        } catch (AuthorizationException $e) {
            return back()->with('error', 'Tidak diizinkan: '.$e->getMessage());
        }

        // Use an explicit redirect (not back()) so Inertia always gets a clean
        // GET to the weekly page with the correct week param and fresh props.
        $weekStart = Carbon::parse($validated['activity_date_start'])
            ->startOfWeek(Carbon::MONDAY)
            ->toDateString();

        return redirect()->route('weekly.index', ['week' => $weekStart])
            ->with('success', 'Kegiatan berhasil disimpan ke rekap mingguan.');
    }
}
