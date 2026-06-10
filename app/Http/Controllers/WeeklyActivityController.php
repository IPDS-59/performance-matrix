<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\SaveActivityClaimAction;
use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Contracts\KipActivitySource;
use App\Models\ActivityClaim;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
                ->where('week_start', $weekStart)
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
                    'project_name' => $plan->project?->name ?? '—',
                    'team_name' => $plan->project?->team?->name ?? $plan->team?->name ?? '—',
                ]);
        }

        $prevWeek = Carbon::parse($weekStart)->subWeek()->toDateString();
        $nextWeek = Carbon::parse($weekStart)->addWeek()->toDateString();

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
        ]);
    }

    public function sync(
        Request $request,
        KipActivitySource $source,
        SyncKipActivitiesAction $action,
    ): RedirectResponse {
        $employee = $request->user()->employee;

        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        if (empty($employee->nip_lama)) {
            return back()->with('error', 'NIP lama belum tersetel, sinkronisasi kipApp tidak dapat dijalankan.');
        }

        try {
            $upserted = $action->execute($source, collect([$employee]));
        } catch (Throwable $e) {
            return back()->with('error', 'Sinkronisasi kipApp gagal: '.$e->getMessage());
        }

        return back()->with('success', "Sinkronisasi selesai. {$upserted} kegiatan diperbarui dari kipApp.");
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
            'obstacle' => ['nullable', 'string'],
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

        try {
            $action->execute($employee, $validated);
        } catch (AuthorizationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Kegiatan berhasil disimpan ke rekap mingguan.');
    }

    public function storeManualActivity(Request $request): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:1000'],
            'activity_date_start' => ['required', 'date'],
            'activity_date_end' => ['nullable', 'date', 'after_or_equal:activity_date_start'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'evidence_url' => ['nullable', 'url', 'max:2048'],
        ]);

        KipActivity::create([
            'employee_id' => $employee->id,
            'nip_lama' => $employee->nip_lama ?? '',
            'external_id' => 'manual-'.(string) Str::uuid(),
            'description' => $validated['description'],
            'activity_date_start' => $validated['activity_date_start'],
            'activity_date_end' => $validated['activity_date_end'] ?? null,
            'time_start' => $validated['start_time'] ?? null,
            'time_end' => $validated['end_time'] ?? null,
            'evidence_url' => $validated['evidence_url'] ?? null,
            'is_claimed' => false,
            'fetched_at' => now(),
            'raw_payload' => null,
        ]);

        return back()->with('success', 'Kegiatan berhasil ditambahkan.');
    }
}
