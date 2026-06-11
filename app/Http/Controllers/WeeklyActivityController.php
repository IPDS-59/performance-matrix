<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\SaveActivityClaimAction;
use App\Models\ActivityClaim;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $activities = $employee
            ? KipActivity::with('claim')
                ->where('employee_id', $employee->id)
                ->whereBetween('activity_date_start', [$weekStart, $weekEnd])
                ->orderBy('activity_date_start')
                ->get()
            : collect();

        $recap = $employee
            ? ActivityClaim::with(['performancePlan.project', 'kipActivity'])
                ->where('employee_id', $employee->id)
                ->whereDate('week_start', $weekStart)
                ->where('status', 'saved')
                ->get()
            : collect();

        $plans = collect();

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
                    'project_name' => $plan->project?->name ?? null,
                    'team_name' => $plan->project?->team?->name ?? $plan->team?->name ?? '—',
                ]);
        }

        $prevWeek = Carbon::parse($weekStart)->subWeek()->toDateString();
        $nextWeek = Carbon::parse($weekStart)->addWeek()->toDateString();

        // PJ (team leader) fills Solusi + RTL; members only fill Kendala.
        $isPj = $employee !== null && (
            Team::where('leader_id', $employee->id)->exists()
            || $employee->teams()->wherePivot('role', 'leader')->exists()
        );

        return Inertia::render('Kinetik/WeeklyScrapper', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'name' => $employee->name,
                'display_name' => $employee->display_name,
            ] : null,
            'activities' => $activities,
            'recap' => $recap,
            'plans' => $plans,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'isPj' => $isPj,
        ]);
    }

    public function storeClaim(Request $request, SaveActivityClaimAction $action): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'kip_activity_id' => ['nullable', 'integer', 'exists:kip_activities,id'],
            'performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
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
        ]);

        $validated['status'] = $validated['status'] ?? 'saved';

        // Solusi & Rencana Tindak Lanjut are PJ-only (filled at the team recap).
        $isPj = Team::where('leader_id', $employee->id)->exists()
            || $employee->teams()->wherePivot('role', 'leader')->exists();
        if (! $isPj) {
            unset($validated['solution'], $validated['follow_up_plan']);
        }

        try {
            $action->execute($employee, $validated);
        } catch (AuthorizationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kegiatan berhasil disimpan ke rekap mingguan.');
    }
}
