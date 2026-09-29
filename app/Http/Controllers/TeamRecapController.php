<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\PrefillRecapAction;
use App\Models\Employee;
use App\Models\LeadershipNote;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\RecapLock;
use App\Models\RecapOverride;
use App\Models\Team;
use App\Models\TeamRecapEvidence;
use App\Models\WeeklyTeamNote;
use App\Services\Kinetik\RecapAggregator;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TeamRecapController extends Controller
{
    private const LOCKED_MESSAGE = 'Rekap periode ini sudah dikunci PJ. Buka kunci terlebih dahulu untuk mengubah.';

    public function __construct(private readonly RecapAggregator $aggregator) {}

    // ── Team weekly recap ────────────────────────────────────────────────────

    public function weekly(Request $request): Response
    {
        $employee = $request->user()->employee;
        $teams = $this->teamsFor($request);
        $team = $this->selectedTeam($request, $teams, $employee);

        $defaultWeek = $team ? $this->aggregator->defaultWeekStart($team) : null;
        $anchor = $request->query('week')
            ? Carbon::parse($request->query('week'))
            : ($defaultWeek ? Carbon::parse($defaultWeek) : now());
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

        $weeklyNote = $team
            ? WeeklyTeamNote::where('team_id', $team->id)
                ->whereDate('week_start', $weekStart)
                ->first()
            : null;

        return Inertia::render('Kinetik/TeamWeeklyRecap', [
            'teams' => $this->teamOptions($teams),
            'selectedTeamId' => $team?->id,
            'segments' => $segments,
            'evidences' => $evidences,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'prevWeek' => Carbon::parse($weekStart)->subWeek()->toDateString(),
            'nextWeek' => Carbon::parse($weekStart)->addWeek()->toDateString(),
            ...$this->lockProps($employee, $team, 'week', (int) Carbon::parse($weekStart)->year, weekStart: $weekStart),
            'currentEmployeeId' => $employee?->id,
            'weeklyNote' => $weeklyNote,
            'members' => $team ? $this->aggregator->memberCompleteness($team, $weekStart) : [],
        ]);
    }

    // ── Team monthly recap ───────────────────────────────────────────────────

    public function monthly(Request $request): Response
    {
        $employee = $request->user()->employee;
        $teams = $this->teamsFor($request);
        $team = $this->selectedTeam($request, $teams, $employee);

        $hasYearParam = $request->query('year') !== null;
        $hasMonthParam = $request->query('month') !== null;

        if (! $hasYearParam && ! $hasMonthParam && $team) {
            $latest = $this->aggregator->latestClaimPeriod($team);
            $year = $latest?->period_year ?? now()->year;
            $month = $latest?->period_month ?? now()->month;
        } else {
            $year = (int) $request->query('year', now()->year);
            $month = (int) $request->query('month', now()->month);
        }

        $segments = $team ? $this->aggregator->monthly($team, $year, $month) : [];

        return Inertia::render('Kinetik/MonthlyRecap', [
            'teams' => $this->teamOptions($teams),
            'selectedTeamId' => $team?->id,
            'segments' => $segments,
            'year' => $year,
            'month' => $month,
            ...$this->lockProps($employee, $team, 'month', $year, month: $month),
            'currentEmployeeId' => $employee?->id,
        ]);
    }

    // ── Team quarterly recap (FRA) ───────────────────────────────────────────

    public function quarterly(Request $request): Response
    {
        $employee = $request->user()->employee;
        $teams = $this->teamsFor($request);
        $team = $this->selectedTeam($request, $teams, $employee);

        $hasYearParam = $request->query('year') !== null;
        $hasQuarterParam = $request->query('quarter') !== null;

        if (! $hasYearParam && ! $hasQuarterParam && $team) {
            $latest = $this->aggregator->latestClaimPeriod($team);
            $year = $latest?->period_year ?? now()->year;
            $quarter = $latest?->period_quarter ?? (int) intdiv(now()->month - 1, 3) + 1;
        } else {
            $year = (int) $request->query('year', now()->year);
            $quarter = (int) $request->query('quarter', (int) intdiv(now()->month - 1, 3) + 1);
        }

        $segments = $team ? $this->aggregator->quarterly($team, $year, $quarter) : [];

        return Inertia::render('Kinetik/QuarterlyRecap', [
            'teams' => $this->teamOptions($teams),
            'selectedTeamId' => $team?->id,
            'segments' => $segments,
            'year' => $year,
            'quarter' => $quarter,
            'pics' => $team ? $this->teamMemberOptions($team) : [],
            ...$this->lockProps($employee, $team, 'quarter', $year, quarter: $quarter),
            'currentEmployeeId' => $employee?->id,
        ]);
    }

    // ── All-teams overview (the head reads the whole office at once) ─────────

    public function overview(Request $request): Response
    {
        $validated = $request->validate([
            'period_type' => ['nullable', 'in:week,month,quarter'],
            'week' => ['nullable', 'date'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
        ]);

        $type = $validated['period_type'] ?? 'month';
        $year = (int) ($validated['year'] ?? now()->year);
        $month = (int) ($validated['month'] ?? now()->month);
        $quarter = (int) ($validated['quarter'] ?? intdiv(now()->month - 1, 3) + 1);
        $weekStart = Carbon::parse($validated['week'] ?? now())->startOfWeek(Carbon::MONDAY)->toDateString();
        if ($type === 'week') {
            $year = (int) Carbon::parse($weekStart)->year;
        }

        // Head notes of this period keyed "team:project" (project 0 = the team).
        $notes = LeadershipNote::forPeriod($type, $year, $month, $quarter, $weekStart)
            ->get()
            ->keyBy(fn (LeadershipNote $note) => $note->team_id.':'.($note->project_id ?? 0))
            ->map(fn (LeadershipNote $note) => $note->body);

        $teams = $this->teamsFor($request)->map(function (Team $team) use ($type, $year, $month, $quarter, $weekStart, $notes) {
            $segments = match ($type) {
                'week' => $this->aggregator->weekly($team, $weekStart),
                'month' => $this->aggregator->monthly($team, $year, $month),
                'quarter' => $this->aggregator->quarterly($team, $year, $quarter),
            };
            $rows = collect($segments)->flatMap(fn (array $segment) => $segment['rows']);
            $bySegment = collect($segments)->keyBy(fn (array $segment) => $segment['project_id'] ?? 0);
            $achievements = $rows->pluck('achievement')->filter(fn ($v) => $v !== null);
            $members = $type === 'week' ? collect($this->aggregator->memberCompleteness($team, $weekStart)) : null;
            $activeMembers = $members?->where('status', '!=', 'no_activity');

            return [
                'id' => $team->id,
                'name' => $team->name,
                'rows' => $rows->count(),
                'confirmed' => $rows->where('is_confirmed', true)->count(),
                'avg_achievement' => $achievements->isEmpty() ? null : round($achievements->avg(), 1),
                'locked' => RecapLock::forPeriod($team->id, $type, $year, $month, $quarter, $weekStart) !== null,
                'members_active' => $activeMembers?->count(),
                'members_complete' => $activeMembers?->where('status', 'complete')->count(),
                'leader' => $team->leader?->display_name ?? $team->leader?->name,
                'note' => $notes->get($team->id.':0'),
                'projects' => collect($this->overviewProjects($team, $year, $bySegment))
                    ->map(fn (array $project) => [...$project, 'note' => $project['id'] ? $notes->get($team->id.':'.$project['id']) : null])
                    ->all(),
            ];
        })->values();

        return Inertia::render('Kinetik/RecapOverview', [
            'periodType' => $type,
            'year' => $year,
            'month' => $month,
            'quarter' => $quarter,
            'weekStart' => $weekStart,
            'weekEnd' => Carbon::parse($weekStart)->endOfWeek(Carbon::SUNDAY)->toDateString(),
            'teams' => $teams,
            'canWriteNotes' => $request->user()->hasRole('head'),
        ]);
    }

    /**
     * The head's note (Catatan Pimpinan) on a team or one of its projects for
     * one period. Allowed on a locked period: the head writes it in the
     * meeting, after the PJ locks the recap. An empty body removes the note.
     */
    public function storeLeadershipNote(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('head'), 403);

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'period_type' => ['required', 'in:week,month,quarter'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'week_start' => ['nullable', 'date', 'required_if:period_type,week'],
            'period_month' => ['nullable', 'integer', 'between:1,12', 'required_if:period_type,month'],
            'period_quarter' => ['nullable', 'integer', 'between:1,4', 'required_if:period_type,quarter'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        if (isset($validated['project_id'])) {
            abort_unless(Project::whereKey($validated['project_id'])->where('team_id', $validated['team_id'])->exists(), 422);
        }

        $type = $validated['period_type'];
        $key = [
            'team_id' => $validated['team_id'],
            'project_id' => $validated['project_id'] ?? null,
            'period_type' => $type,
            'period_year' => $validated['period_year'],
            'week_start' => $type === 'week' ? Carbon::parse($validated['week_start'])->startOfWeek(Carbon::MONDAY)->toDateString() : null,
            'period_month' => $type === 'month' ? $validated['period_month'] : null,
            'period_quarter' => $type === 'quarter' ? $validated['period_quarter'] : null,
        ];
        $body = trim($validated['body'] ?? '');

        if ($body === '') {
            LeadershipNote::where($key)->delete();

            return back()->with('success', 'Catatan pimpinan dihapus.');
        }

        LeadershipNote::updateOrCreate($key, ['body' => $body, 'author_id' => $request->user()->id]);

        return back()->with('success', 'Catatan pimpinan disimpan.');
    }

    /**
     * The team's projects for the overview: who is in charge (PIC) and how the
     * period looks. Recap rows without a Projek are listed last.
     *
     * @param  Collection<int|string, array<string, mixed>>  $bySegment  recap segments keyed by project id (0 = none)
     * @return array<int, array<string, mixed>>
     */
    private function overviewProjects(Team $team, int $year, Collection $bySegment): array
    {
        $summary = function (?array $segment): array {
            $rows = collect($segment['rows'] ?? []);
            $values = $rows->pluck('achievement')->filter(fn ($v) => $v !== null);

            return [
                'rows' => $rows->count(),
                'avg_achievement' => $values->isEmpty() ? null : round($values->avg(), 1),
            ];
        };

        $projects = $team->projects()
            ->with('leader:id,name,display_name')
            ->withCount('members')
            ->where('year', $year)
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'leader' => $project->leader?->display_name ?? $project->leader?->name,
                'members' => $project->members_count,
                ...$summary($bySegment->get($project->id)),
            ]);

        if ($bySegment->has(0)) {
            $projects->push(['id' => null, 'name' => 'Tanpa projek', 'leader' => null, 'members' => null, ...$summary($bySegment->get(0))]);
        }

        return $projects->values()->all();
    }

    // ── Excel export (old Rapat Mingguan / Rapat Bulanan / FRA layout) ──────

    /**
     * Recap data for every team the viewer can see, one entry per team, so the
     * client can write a single office-wide sheet like the old spreadsheets.
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period_type' => ['required', 'in:week,month,quarter'],
            'week' => ['nullable', 'date', 'required_if:period_type,week'],
            'year' => ['nullable', 'integer', 'required_unless:period_type,week'],
            'month' => ['nullable', 'integer', 'between:1,12', 'required_if:period_type,month'],
            'quarter' => ['nullable', 'integer', 'between:1,4', 'required_if:period_type,quarter'],
        ]);

        $type = $validated['period_type'];
        $weekStart = $type === 'week'
            ? Carbon::parse($validated['week'])->startOfWeek(Carbon::MONDAY)->toDateString()
            : null;

        $teams = $this->teamsFor($request)->map(function (Team $team) use ($type, $validated, $weekStart) {
            $segments = match ($type) {
                'week' => $this->aggregator->weekly($team, $weekStart),
                'month' => $this->aggregator->monthly($team, (int) $validated['year'], (int) $validated['month']),
                'quarter' => $this->aggregator->quarterly($team, (int) $validated['year'], (int) $validated['quarter']),
            };

            $evidences = $type === 'week'
                ? TeamRecapEvidence::where('team_id', $team->id)
                    ->where('period_type', 'week')
                    ->whereDate('week_start', $weekStart)
                    ->get(['type', 'title', 'url'])
                    ->groupBy('type')
                    ->map(fn (Collection $items) => $items->pluck('url')->values())
                : collect();

            return [
                'team_name' => $team->name,
                'segments' => $segments,
                'evidences' => $evidences,
            ];
        })->values();

        return response()->json([
            'period_type' => $type,
            'week_start' => $weekStart,
            'teams' => $teams,
        ]);
    }

    // ── Lock (PJ freezes a period before the meeting) ────────────────────────

    public function toggleLock(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'period_type' => ['required', 'in:week,month,quarter'],
            'period_year' => ['required', 'integer'],
            'period_month' => ['nullable', 'integer', 'between:1,12', 'required_if:period_type,month'],
            'period_quarter' => ['nullable', 'integer', 'between:1,4', 'required_if:period_type,quarter'],
            'week_start' => ['nullable', 'date', 'required_if:period_type,week'],
            'locked' => ['required', 'boolean'],
        ]);

        $this->authorizePj($employee, (int) $validated['team_id']);

        $existing = $this->lockFor($validated);

        if (! $validated['locked']) {
            $existing?->delete();

            return back()->with('success', 'Kunci rekap dibuka.');
        }

        if ($existing === null) {
            $type = $validated['period_type'];
            RecapLock::create([
                'team_id' => $validated['team_id'],
                'period_type' => $type,
                'period_year' => $validated['period_year'],
                'week_start' => $type === 'week' ? Carbon::parse($validated['week_start'])->toDateString() : null,
                'period_month' => $type === 'month' ? $validated['period_month'] : null,
                'period_quarter' => $type === 'quarter' ? $validated['period_quarter'] : null,
                'locked_by' => $employee->id,
            ]);
        }

        return back()->with('success', 'Rekap dikunci. Rekap tidak dapat diubah sampai kunci dibuka.');
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

        $this->authorizePj($employee, (int) $validated['team_id']);

        $weekStart = Carbon::parse($validated['week_start']);
        $this->ensureUnlocked((int) $validated['team_id'], 'week', $weekStart->year, weekStart: $weekStart->toDateString());

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

        $this->authorizePj($employee, $evidence->team_id);
        $this->ensureUnlocked($evidence->team_id, 'week', $evidence->period_year, weekStart: Carbon::parse($evidence->week_start)->toDateString());

        $evidence->delete();

        return back()->with('success', 'Bukti dukung berhasil dihapus.');
    }

    // ── Single weekly PJ note ────────────────────────────────────────────────

    public function storeWeeklyNote(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'week_start' => ['required', 'date'],
            'uraian' => ['nullable', 'string'],
            'obstacle' => ['nullable', 'string'],
            'solution' => ['nullable', 'string'],
            'follow_up_plan' => ['nullable', 'string'],
        ]);

        $this->authorizePj($employee, (int) $validated['team_id']);
        $this->ensureUnlocked((int) $validated['team_id'], 'week', Carbon::parse($validated['week_start'])->year, weekStart: $validated['week_start']);

        WeeklyTeamNote::updateOrCreate(
            [
                'team_id' => $validated['team_id'],
                'week_start' => $validated['week_start'],
            ],
            [
                'uraian' => $validated['uraian'] ?? null,
                'obstacle' => $validated['obstacle'] ?? null,
                'solution' => $validated['solution'] ?? null,
                'follow_up_plan' => $validated['follow_up_plan'] ?? null,
                'created_by' => $employee->id,
            ],
        );

        return back()->with('success', 'Catatan mingguan berhasil disimpan.');
    }

    // ── Paraphrase / FRA follow-up override ──────────────────────────────────

    public function storeOverride(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'period_type' => ['required', 'in:week,month,quarter'],
            'period_year' => ['nullable', 'integer'],
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'period_quarter' => ['nullable', 'integer', 'between:1,4'],
            'week_start' => ['nullable', 'date', 'required_if:period_type,week'],
            'uraian' => ['nullable', 'string'],
            'obstacle' => ['nullable', 'string'],
            'solution' => ['nullable', 'string'],
            'follow_up_plan' => ['nullable', 'string'],
            'follow_up_evidence_url' => ['nullable', 'url', 'max:2048'],
            'follow_up_pic_employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'follow_up_deadline' => ['nullable', 'date'],
        ]);

        if (($validated['period_type'] ?? '') === 'week' && empty($validated['period_year'])) {
            $validated['period_year'] = Carbon::parse($validated['week_start'])->year;
        }

        $plan = PerformancePlan::findOrFail((int) $validated['performance_plan_id']);
        $this->authorizeParaphrase($employee, (int) $validated['team_id'], $plan);
        $this->ensureUnlockedFor($validated);

        RecapOverride::updateOrCreate(
            [
                'team_id' => $validated['team_id'],
                'performance_plan_id' => $validated['performance_plan_id'],
                'project_id' => $validated['project_id'] ?? null,
                'period_type' => $validated['period_type'],
                'period_year' => $validated['period_year'],
                'period_month' => $validated['period_month'] ?? null,
                'period_quarter' => $validated['period_quarter'] ?? null,
                'week_start' => $validated['week_start'] ?? null,
            ],
            [
                'uraian' => $validated['uraian'] ?? null,
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

    // ── Pre-fill from lower periods ──────────────────────────────────────────

    /**
     * Fill the empty text of a monthly recap from its weeks, or of a quarterly
     * recap from its months. PJ only; blocked while the period is locked.
     */
    public function prefill(Request $request, PrefillRecapAction $prefill): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'period_type' => ['required', 'in:month,quarter'],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'period_month' => ['nullable', 'integer', 'between:1,12', 'required_if:period_type,month'],
            'period_quarter' => ['nullable', 'integer', 'between:1,4', 'required_if:period_type,quarter'],
        ]);

        $this->authorizePj($employee, (int) $validated['team_id']);
        $this->ensureUnlockedFor($validated);

        $type = $validated['period_type'];
        $filled = $prefill->execute(
            Team::findOrFail($validated['team_id']),
            $employee,
            $type,
            (int) $validated['period_year'],
            $type === 'month' ? (int) $validated['period_month'] : null,
            $type === 'quarter' ? (int) $validated['period_quarter'] : null,
        );

        $source = $type === 'month' ? 'mingguan' : 'bulanan';

        return back()->with('success', $filled
            ? "{$filled} baris diisi dari rekap {$source}. Teks yang sudah ada tidak diubah."
            : "Tidak ada teks {$source} baru untuk diisi.");
    }

    // ── Bulk confirm (achievement ≥ 100%) ────────────────────────────────────

    public function confirmBulk(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'period_type' => ['required', 'in:week,month,quarter'],
            'period_year' => ['required', 'integer'],
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'period_quarter' => ['nullable', 'integer', 'between:1,4'],
            'week_start' => ['nullable', 'date', 'required_if:period_type,week'],
            'performance_plan_ids' => ['required', 'array'],
            'performance_plan_ids.*' => ['integer', 'exists:performance_plans,id'],
            // Parallel to performance_plan_ids: the Projek of each row (nullable).
            'project_ids' => ['sometimes', 'array'],
            'project_ids.*' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $this->authorizePj($employee, (int) $validated['team_id']);
        $this->ensureUnlockedFor($validated);

        $periodKey = [
            'team_id' => $validated['team_id'],
            'period_type' => $validated['period_type'],
            'period_year' => $validated['period_year'],
            'period_month' => $validated['period_month'] ?? null,
            'period_quarter' => $validated['period_quarter'] ?? null,
            'week_start' => $validated['week_start'] ?? null,
        ];

        DB::transaction(function () use ($validated, $periodKey, $employee) {
            foreach ($validated['performance_plan_ids'] as $i => $planId) {
                RecapOverride::updateOrCreate(
                    array_merge($periodKey, [
                        'performance_plan_id' => $planId,
                        'project_id' => $validated['project_ids'][$i] ?? null,
                    ]),
                    ['confirmed_at' => now(), 'confirmed_by' => $employee->id],
                );
            }
        });

        $count = count($validated['performance_plan_ids']);

        return back()->with('success', "{$count} RK berhasil dikonfirmasi.");
    }

    // ── Confirm / unconfirm individual RK row ─────────────────────────────────

    public function confirmOverride(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $validated = $request->validate([
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'period_type' => ['required', 'in:week,month,quarter'],
            'period_year' => ['required', 'integer'],
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'period_quarter' => ['nullable', 'integer', 'between:1,4'],
            'week_start' => ['nullable', 'date', 'required_if:period_type,week'],
            'confirmed' => ['required', 'boolean'],
        ]);

        $this->authorizePj($employee, (int) $validated['team_id']);
        $this->ensureUnlockedFor($validated);

        $key = [
            'team_id' => $validated['team_id'],
            'performance_plan_id' => $validated['performance_plan_id'],
            'project_id' => $validated['project_id'] ?? null,
            'period_type' => $validated['period_type'],
            'period_year' => $validated['period_year'],
            'period_month' => $validated['period_month'] ?? null,
            'period_quarter' => $validated['period_quarter'] ?? null,
            'week_start' => $validated['week_start'] ?? null,
        ];

        RecapOverride::updateOrCreate($key, [
            'confirmed_at' => $validated['confirmed'] ? now() : null,
            'confirmed_by' => $validated['confirmed'] ? $employee->id : null,
        ]);

        return back()->with('success', $validated['confirmed'] ? 'RK dikonfirmasi.' : 'Konfirmasi dibatalkan.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * @return Collection<int, Team>
     */
    private function teamsFor(Request $request): Collection
    {
        // The head reads every team's recap in the office-wide meeting, as the
        // old Rapat Mingguan / Rapat Bulanan sheets did. Read-only for them.
        if ($request->user()->hasAnyRole(['admin', 'head'])) {
            return Team::orderBy('name')->get();
        }

        $employee = $request->user()->employee;

        if ($employee === null) {
            return collect();
        }

        return $employee->teams()->orderBy('teams.name')->get();
    }

    /**
     * Resolve the active team from the request. When no ?team param is present,
     * prefers a team the employee leads; falls back to alphabetical first.
     *
     * @param  Collection<int, Team>  $teams
     */
    private function selectedTeam(Request $request, Collection $teams, ?Employee $employee = null): ?Team
    {
        $requested = $request->query('team');

        if ($requested !== null) {
            return $teams->firstWhere('id', (int) $requested) ?? $teams->first();
        }

        if ($employee !== null) {
            $led = $teams->first(fn (Team $t) => $this->isPj($employee, $t->id));
            if ($led !== null) {
                return $led;
            }
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

    /**
     * PJ = team leader (teams.leader_id) or a member with the 'leader' pivot role.
     * Per the RFC, only the PJ may upload meeting evidence and paraphrase recaps.
     */
    private function isPj(Employee $employee, int $teamId): bool
    {
        return Team::where('id', $teamId)->where('leader_id', $employee->id)->exists()
            || $employee->teams()
                ->where('teams.id', $teamId)
                ->wherePivot('role', 'leader')
                ->exists();
    }

    /**
     * Lock state for a rendered recap. `canManage` is false while locked so
     * every edit control hides; `canLock` lets the PJ still unlock.
     *
     * @return array{lock: array{locked_at: string|null, locked_by: string|null}|null, canManage: bool, canLock: bool}
     */
    private function lockProps(?Employee $employee, ?Team $team, string $type, int $year, ?int $month = null, ?int $quarter = null, ?string $weekStart = null): array
    {
        $lock = $team ? RecapLock::forPeriod($team->id, $type, $year, $month, $quarter, $weekStart) : null;
        $isPj = $team !== null && $employee !== null && $this->isPj($employee, $team->id);

        return [
            'lock' => $lock ? [
                'locked_at' => $lock->created_at?->toIso8601String(),
                'locked_by' => $lock->lockedBy?->display_name ?? $lock->lockedBy?->name,
            ] : null,
            'canManage' => $isPj && $lock === null,
            'canLock' => $isPj,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated  period fields as validated by the recap endpoints
     */
    private function lockFor(array $validated): ?RecapLock
    {
        $type = $validated['period_type'];
        $weekStart = isset($validated['week_start']) ? Carbon::parse($validated['week_start'])->toDateString() : null;
        $year = $validated['period_year'] ?? ($weekStart ? Carbon::parse($weekStart)->year : null);

        return RecapLock::forPeriod(
            (int) $validated['team_id'],
            $type,
            (int) $year,
            isset($validated['period_month']) ? (int) $validated['period_month'] : null,
            isset($validated['period_quarter']) ? (int) $validated['period_quarter'] : null,
            $weekStart,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function ensureUnlockedFor(array $validated): void
    {
        if ($this->lockFor($validated) !== null) {
            throw new HttpResponseException(back()->with('error', self::LOCKED_MESSAGE));
        }
    }

    private function ensureUnlocked(int $teamId, string $type, int $year, ?int $month = null, ?int $quarter = null, ?string $weekStart = null): void
    {
        if (RecapLock::forPeriod($teamId, $type, $year, $month, $quarter, $weekStart) !== null) {
            throw new HttpResponseException(back()->with('error', self::LOCKED_MESSAGE));
        }
    }

    private function authorizePj(Employee $employee, int $teamId): void
    {
        abort_unless(
            $this->isPj($employee, $teamId),
            403,
            'Hanya PJ / Ketua Tim yang dapat mengelola bukti dan parafrase rekap.',
        );
    }

    /**
     * For paraphrase drafting: the PJ OR the RK's assigned PIC may draft.
     */
    private function canParaphrasePlan(Employee $employee, int $teamId, PerformancePlan $plan): bool
    {
        return $this->isPj($employee, $teamId) || $plan->pic_employee_id === $employee->id;
    }

    private function authorizeParaphrase(Employee $employee, int $teamId, PerformancePlan $plan): void
    {
        abort_unless(
            $this->canParaphrasePlan($employee, $teamId, $plan),
            403,
            'Hanya PJ atau PIC RK yang dapat membuat parafrase rekap.',
        );
    }
}
