<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\RecapOverride;
use App\Models\Team;
use App\Models\TeamRecapEvidence;
use App\Services\Kinetik\RecapAggregator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TeamRecapController extends Controller
{
    public function __construct(private readonly RecapAggregator $aggregator) {}

    // ── Team weekly recap ────────────────────────────────────────────────────

    public function weekly(Request $request): Response
    {
        $employee = $request->user()->employee;
        $teams = $this->teamsFor($employee);
        $team = $this->selectedTeam($request, $teams);

        $anchor = $request->query('week') ? Carbon::parse($request->query('week')) : now();
        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = $anchor->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $segments = $team ? $this->aggregator->weekly($team, $weekStart) : [];

        $evidences = $team
            ? TeamRecapEvidence::where('team_id', $team->id)
                ->where('period_type', 'week')
                ->whereDate('week_start', $weekStart)
                ->latest()
                ->get()
            : collect();

        return Inertia::render('Kinetik/TeamWeeklyRecap', [
            'teams' => $this->teamOptions($teams),
            'selectedTeamId' => $team?->id,
            'segments' => $segments,
            'evidences' => $evidences,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevWeek' => Carbon::parse($weekStart)->subWeek()->toDateString(),
            'nextWeek' => Carbon::parse($weekStart)->addWeek()->toDateString(),
        ]);
    }

    // ── Team monthly recap ───────────────────────────────────────────────────

    public function monthly(Request $request): Response
    {
        $employee = $request->user()->employee;
        $teams = $this->teamsFor($employee);
        $team = $this->selectedTeam($request, $teams);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        $segments = $team ? $this->aggregator->monthly($team, $year, $month) : [];

        return Inertia::render('Kinetik/MonthlyRecap', [
            'teams' => $this->teamOptions($teams),
            'selectedTeamId' => $team?->id,
            'segments' => $segments,
            'year' => $year,
            'month' => $month,
        ]);
    }

    // ── Team quarterly recap (FRA) ───────────────────────────────────────────

    public function quarterly(Request $request): Response
    {
        $employee = $request->user()->employee;
        $teams = $this->teamsFor($employee);
        $team = $this->selectedTeam($request, $teams);

        $year = (int) $request->query('year', now()->year);
        $quarter = (int) $request->query('quarter', (int) intdiv(now()->month - 1, 3) + 1);

        $segments = $team ? $this->aggregator->quarterly($team, $year, $quarter) : [];

        return Inertia::render('Kinetik/QuarterlyRecap', [
            'teams' => $this->teamOptions($teams),
            'selectedTeamId' => $team?->id,
            'segments' => $segments,
            'year' => $year,
            'quarter' => $quarter,
            'pics' => $team ? $this->teamMemberOptions($team) : [],
        ]);
    }

    // ── Evidence (notula / photo / attendance) ───────────────────────────────

    public function storeEvidence(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'week_start' => ['required', 'date'],
            'type' => ['required', 'in:notula,photo,attendance'],
            'title' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $this->authorizeMembership($employee, (int) $validated['team_id']);

        $weekStart = Carbon::parse($validated['week_start']);

        TeamRecapEvidence::create([
            'team_id' => $validated['team_id'],
            'project_id' => $validated['project_id'] ?? null,
            'period_type' => 'week',
            'period_year' => (int) $weekStart->year,
            'week_start' => $weekStart->toDateString(),
            'type' => $validated['type'],
            'title' => $validated['title'] ?? null,
            'url' => $validated['url'],
            'uploaded_by' => $employee->id,
        ]);

        return back()->with('success', 'Bukti dukung berhasil ditambahkan.');
    }

    public function destroyEvidence(Request $request, TeamRecapEvidence $evidence): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $this->authorizeMembership($employee, $evidence->team_id);

        $evidence->delete();

        return back()->with('success', 'Bukti dukung berhasil dihapus.');
    }

    // ── Paraphrase / FRA follow-up override ──────────────────────────────────

    public function storeOverride(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
            'period_type' => ['required', 'in:month,quarter'],
            'period_year' => ['required', 'integer'],
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'period_quarter' => ['nullable', 'integer', 'between:1,4'],
            'obstacle' => ['nullable', 'string'],
            'solution' => ['nullable', 'string'],
            'follow_up_plan' => ['nullable', 'string'],
            'follow_up_evidence_url' => ['nullable', 'url', 'max:2048'],
            'follow_up_pic_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'follow_up_deadline' => ['nullable', 'date'],
        ]);

        $this->authorizeMembership($employee, (int) $validated['team_id']);

        RecapOverride::updateOrCreate(
            [
                'team_id' => $validated['team_id'],
                'performance_plan_id' => $validated['performance_plan_id'],
                'period_type' => $validated['period_type'],
                'period_year' => $validated['period_year'],
                'period_month' => $validated['period_month'] ?? null,
                'period_quarter' => $validated['period_quarter'] ?? null,
            ],
            [
                'obstacle' => $validated['obstacle'] ?? null,
                'solution' => $validated['solution'] ?? null,
                'follow_up_plan' => $validated['follow_up_plan'] ?? null,
                'follow_up_evidence_url' => $validated['follow_up_evidence_url'] ?? null,
                'follow_up_pic_employee_id' => $validated['follow_up_pic_employee_id'] ?? null,
                'follow_up_deadline' => $validated['follow_up_deadline'] ?? null,
                'created_by' => $employee->id,
            ],
        );

        return back()->with('success', 'Rekap berhasil diparafrase.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Team>
     */
    private function teamsFor(?Employee $employee): Collection
    {
        if ($employee === null) {
            return collect();
        }

        return $employee->teams()->orderBy('teams.name')->get();
    }

    /**
     * @param  Collection<int, Team>  $teams
     */
    private function selectedTeam(Request $request, Collection $teams): ?Team
    {
        $requested = $request->query('team');

        if ($requested !== null) {
            return $teams->firstWhere('id', (int) $requested) ?? $teams->first();
        }

        return $teams->first();
    }

    /**
     * @param  Collection<int, Team>  $teams
     * @return array<int, array{id: int, name: string}>
     */
    private function teamOptions(Collection $teams): array
    {
        return $teams->map(fn (Team $t) => ['id' => $t->id, 'name' => $t->name])->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function teamMemberOptions(Team $team): array
    {
        return $team->members()
            ->orderBy('employees.name')
            ->get()
            ->map(fn (Employee $e) => ['id' => $e->id, 'name' => $e->display_name ?? $e->name])
            ->all();
    }

    private function authorizeMembership(Employee $employee, int $teamId): void
    {
        $isMember = $employee->teams()->where('teams.id', $teamId)->exists();

        abort_unless($isMember, 403, 'Anda tidak memiliki akses ke rekap tim ini.');
    }
}
