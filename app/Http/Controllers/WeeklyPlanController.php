<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTeams;
use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\KipActivity;
use App\Models\Team;
use App\Models\WeeklyFocus;
use App\Notifications\KinetikNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Rencana Minggu Ini": for each team member, the PJ's focus for the week,
 * the RK with no kegiatan yet this quarter, and the kegiatan still under 100%.
 * Read from the synced kipApp data; nothing is written to kipApp.
 */
class WeeklyPlanController extends Controller
{
    use ResolvesTeams;

    public function index(Request $request): Response
    {
        $employee = $request->user()->employee;
        $teams = $this->teamsFor($request);
        $team = $this->selectedTeam($request, $teams, $employee);

        $monday = Carbon::parse($request->query('week', now()->toDateString()))->startOfWeek(Carbon::MONDAY);
        $weekStart = $monday->toDateString();

        return Inertia::render('Kinetik/WeeklyPlan', [
            'teams' => $this->teamOptions($teams),
            'selectedTeamId' => $team?->id,
            'weekStart' => $weekStart,
            'weekEnd' => $monday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            'prevWeek' => $monday->copy()->subWeek()->toDateString(),
            'nextWeek' => $monday->copy()->addWeek()->toDateString(),
            'quarter' => $monday->quarter,
            'canManage' => $team !== null && $employee !== null && $this->isPj($employee, $team->id),
            'currentEmployeeId' => $employee?->id,
            'members' => $team ? $this->members($team, $monday) : [],
        ]);
    }

    /**
     * The PJ sets (or clears, with an empty text) a member's focus for a week.
     */
    public function storeFocus(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'week_start' => ['required', 'date'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $team = Team::findOrFail($validated['team_id']);
        abort_unless($this->isPj($employee, $team->id), 403, 'Hanya PJ yang dapat mengisi fokus minggu ini.');
        abort_unless($team->members()->where('employees.id', $validated['employee_id'])->exists(), 422, 'Pegawai ini bukan anggota tim.');

        $key = [
            'team_id' => $team->id,
            'employee_id' => (int) $validated['employee_id'],
        ];
        $weekStart = Carbon::parse($validated['week_start'])->startOfWeek(Carbon::MONDAY)->toDateString();
        $existing = WeeklyFocus::where($key)->whereDate('week_start', $weekStart)->first();
        $body = trim((string) ($validated['body'] ?? ''));

        if ($body === '') {
            $existing?->delete();

            return back()->with('success', 'Fokus minggu ini dihapus.');
        }

        $changed = $existing?->body !== $body;
        if ($existing) {
            $existing->update(['body' => $body, 'created_by' => $employee->id]);
        } else {
            WeeklyFocus::create([...$key, 'week_start' => $weekStart, 'body' => $body, 'created_by' => $employee->id]);
        }

        $member = Employee::find($key['employee_id']);
        if ($changed && $member?->user && $member->user_id !== $request->user()->id) {
            $member->user->notify(new KinetikNotification(
                'weekly_focus',
                ($employee->display_name ?? $employee->name).' mengisi fokus Anda untuk minggu '.Carbon::parse($weekStart)->locale('id')->translatedFormat('j M').': '.Str::limit($body, 140),
                route('weekly-plan.index', ['team' => $team->id, 'week' => $weekStart]),
            ));
        }

        return back()->with('success', 'Fokus minggu ini disimpan.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function members(Team $team, Carbon $monday): array
    {
        $members = $team->members()->orderBy('employees.name')->get();
        $ids = $members->pluck('id');
        $quarterStart = $monday->copy()->firstOfQuarter()->toDateString();
        $quarterEnd = $monday->copy()->lastOfQuarter()->toDateString();

        $focus = WeeklyFocus::where('team_id', $team->id)
            ->whereDate('week_start', $monday->toDateString())
            ->pluck('body', 'employee_id');

        $rks = EmployeeRk::whereIn('employee_id', $ids)
            ->where('year', $monday->year)
            ->when($team->kip_external_id, fn ($q) => $q->where('team_kip_id', $team->kip_external_id))
            ->get()
            ->groupBy('employee_id');

        $activities = KipActivity::whereIn('employee_id', $ids)
            ->duringWeek($quarterStart, $quarterEnd)
            ->orderByDesc('activity_date_start')
            ->get(['id', 'employee_id', 'description', 'activity_date_start', 'activity_date_end', 'progress', 'rk_external_id', 'rk_name', 'sent_at', 'evidence_url'])
            ->groupBy('employee_id');

        return $members->map(function (Employee $member) use ($focus, $rks, $activities) {
            $memberRks = $rks->get($member->id, collect());
            $memberActivities = $activities->get($member->id, collect());

            // Only this team's kegiatan when the member's RK list is known.
            $teamActivities = $memberRks->isEmpty() ? $memberActivities : $memberActivities->filter(
                fn (KipActivity $a) => $memberRks->contains(fn (EmployeeRk $rk) => self::sameRk($rk, $a)),
            );

            return [
                'employee_id' => $member->id,
                'name' => $member->display_name ?? $member->name,
                'focus' => $focus->get($member->id),
                'rks_without_activity' => $memberRks
                    ->reject(fn (EmployeeRk $rk) => $memberActivities->contains(fn (KipActivity $a) => self::sameRk($rk, $a)))
                    ->map(fn (EmployeeRk $rk) => ['id' => $rk->id, 'name' => $rk->name])
                    ->values()
                    ->all(),
                'unfinished' => $teamActivities
                    ->filter(fn (KipActivity $a) => (float) $a->progress < 100)
                    ->take(10)
                    ->map(fn (KipActivity $a) => [
                        'id' => $a->id,
                        'description' => $a->description,
                        'date_start' => Carbon::parse($a->activity_date_start)->toDateString(),
                        'progress' => (float) $a->progress,
                        'rk_name' => $a->rk_name,
                        'evidence_url' => $a->evidence_url,
                    ])
                    ->values()
                    ->all(),
                'unsent_count' => $teamActivities->whereNull('sent_at')->count(),
                'activity_count' => $teamActivities->count(),
                'rk_count' => $memberRks->count(),
            ];
        })->all();
    }

    /** kipApp RK ids are per employee; the text is the fallback. */
    private static function sameRk(EmployeeRk $rk, KipActivity $activity): bool
    {
        return ($activity->rk_external_id !== null && (string) $activity->rk_external_id === $rk->kip_rk_id)
            || self::norm($activity->rk_name) === self::norm($rk->name);
    }

    private static function norm(?string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
    }
}
