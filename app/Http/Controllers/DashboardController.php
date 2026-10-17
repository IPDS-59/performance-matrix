<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        if ($user->hasRole('staff')) {
            return $this->staffDashboard($user, $year, $month);
        }

        if ($user->hasRole('head')) {
            return $this->headDashboard($user, $year, $month);
        }

        return $this->adminDashboard($year, $month);
    }

    public function matrix(Request $request): Response
    {
        // Matrix is visible to all authenticated users

        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $teamId = $request->integer('team_id');

        $employees = Employee::query()
            ->where('is_active', true)
            ->when($teamId, fn ($q) => $q->whereHas('projects', fn ($q2) => $q2->where('team_id', $teamId)->where('year', $year)))
            ->orderBy('name')
            ->get(['id', 'name', 'display_name']);

        $projects = Project::with('workItems')
            ->where('year', $year)
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('name')
            ->get();

        // Assignment matrix: which employees are in which projects
        $assignments = DB::table('project_members')
            ->whereIn('project_id', $projects->pluck('id'))
            ->get(['project_id', 'employee_id', 'role'])
            ->groupBy('project_id');

        // Progress: avg achievement per employee per project (so each person sees only their own progress)
        $progress = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->whereIn('work_items.project_id', $projects->pluck('id'))
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->whereNotNull('performance_reports.reported_by')
            ->groupBy('work_items.project_id', 'performance_reports.reported_by')
            ->select(
                'work_items.project_id',
                'performance_reports.reported_by',
                DB::raw('AVG(achievement_percentage) as avg_achievement')
            )
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->reported_by}:{$row->project_id}" => (float) $row->avg_achievement]);

        $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $currentEmployeeId = $request->user()->employee?->id;

        return Inertia::render('Matrix/Index', compact(
            'employees', 'projects', 'assignments', 'progress', 'teams', 'year', 'month', 'teamId', 'currentEmployeeId'
        ));
    }

    /** Capaian against target of one plan item, in percent. */
    private const ACHIEVEMENT_SQL = 'plan_items.capaian * 100.0 / plan_items.target';

    /**
     * Plan items of a month that have both a target and a capaian, so each has
     * an achievement. Cancelled ones are left out; the month is the start date.
     */
    private function achievementItems(int $year, int $month): Builder
    {
        $start = Carbon::create($year, $month, 1);

        return DB::table('plan_items')
            ->where('plan_items.status', '!=', 'cancelled')
            ->where('plan_items.target', '>', 0)
            ->whereNotNull('plan_items.capaian')
            ->whereDate('plan_items.date_start', '>=', $start->toDateString())
            ->whereDate('plan_items.date_start', '<=', $start->copy()->endOfMonth()->toDateString());
    }

    /**
     * Compute team achievement from the members' plan items.
     * Average achievement per team across the period.
     */
    private function computeTeamProgress(int $year, int $month): Collection
    {
        return $this->achievementItems($year, $month)
            ->whereNotNull('plan_items.team_id')
            ->groupBy('plan_items.team_id')
            ->select(
                'plan_items.team_id',
                DB::raw('AVG('.self::ACHIEVEMENT_SQL.') as avg_achievement'),
                DB::raw('COUNT(DISTINCT plan_items.performance_plan_id) as report_count'),
            )
            ->get()
            ->map(fn ($row) => (object) [
                'team_id' => (int) $row->team_id,
                'avg_achievement' => (float) $row->avg_achievement,
                'report_count' => (int) $row->report_count,
            ])
            ->keyBy('team_id');
    }

    /**
     * Compute organisation average as: avg of team averages.
     */
    private function computeOrgAvg(Collection $teamProgress): float
    {
        if ($teamProgress->isEmpty()) {
            return 0;
        }

        return $teamProgress->avg('avg_achievement');
    }

    /**
     * Top employees ranked by average achievement of their plan items.
     */
    private function topEmployeesByAchievement(int $year, int $month, int $limit = 10): Collection
    {
        return $this->achievementItems($year, $month)
            ->join('employees', 'employees.id', '=', 'plan_items.employee_id')
            ->where('employees.is_active', true)
            ->groupBy('plan_items.employee_id', 'employees.name', 'employees.display_name')
            ->select(
                'plan_items.employee_id as id',
                'employees.name',
                'employees.display_name',
                DB::raw('AVG('.self::ACHIEVEMENT_SQL.') as avg_achievement')
            )
            ->orderByDesc('avg_achievement')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (object) [
                'id' => (int) $row->id,
                'name' => $row->name,
                'display_name' => $row->display_name,
                'avg_achievement' => round((float) $row->avg_achievement, 2),
            ]);
    }

    /**
     * Load teams with their active members sourced from project membership (current year).
     * This is more accurate than using the team_id FK on employees, which can be stale.
     *
     * @return Collection<int, Team>
     */
    private function loadTeamsWithMembers(int $year): Collection
    {
        $teams = Team::orderBy('name')->get(['id', 'name', 'code', 'leader_id']);

        $membersByTeam = DB::table('employees')
            ->select('employees.id', 'employees.name', 'employees.display_name', 'projects.team_id')
            ->join('project_members', 'project_members.employee_id', '=', 'employees.id')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('projects.year', $year)
            ->whereIn('projects.team_id', $teams->pluck('id'))
            ->where('employees.is_active', true)
            ->distinct()
            ->get()
            ->groupBy(fn ($m) => (string) $m->team_id);

        $teams->each(function ($team) use ($membersByTeam) {
            $rows = $membersByTeam->get((string) $team->id, collect());
            $team->setRelation('employees', Employee::hydrate(
                $rows->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'display_name' => $m->display_name])->all()
            ));
        });

        return $teams;
    }

    private function personalStats(Employee $employee, int $year, int $month): array
    {
        $isTeamLead = Team::where('leader_id', $employee->id)->exists();

        $myTeamId = $isTeamLead
            ? Team::where('leader_id', $employee->id)->value('id')
            : $employee->team_id;

        // Count actual project memberships and distinct teams for the current year
        $membershipBase = DB::table('project_members')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('project_members.employee_id', $employee->id)
            ->where('projects.year', $year);

        $totalProjects = (clone $membershipBase)->count();
        $totalTeams = (clone $membershipBase)->distinct()->count('projects.team_id');

        // Item Kerja: kip_activities for the year — same source as the "Kegiatan Saya"
        // weekly page, so the numbers are consistent.
        if ($isTeamLead && $myTeamId) {
            $totalItems = DB::table('kip_activities')
                ->join('employees', 'employees.id', '=', 'kip_activities.employee_id')
                ->join('project_members', 'project_members.employee_id', '=', 'employees.id')
                ->join('projects', 'projects.id', '=', 'project_members.project_id')
                ->where('projects.team_id', $myTeamId)
                ->where('projects.year', $year)
                ->where('kip_activities.source_year', $year)
                ->distinct()
                ->count('kip_activities.id');
        } else {
            $totalItems = DB::table('kip_activities')
                ->where('employee_id', $employee->id)
                ->where('source_year', $year)
                ->count();
        }

        // Average achievement of the plan items this period
        $items = $this->achievementItems($year, $month);
        $avgResult = ($isTeamLead && $myTeamId
            ? $items->where('plan_items.team_id', $myTeamId) // PJ: the whole team
            : $items->where('plan_items.employee_id', $employee->id) // member: only their own
        )->selectRaw('AVG('.self::ACHIEVEMENT_SQL.') as avg_achievement')->first();

        return [
            'teams_count' => (int) $totalTeams,
            'projects_count' => (int) $totalProjects,
            'items_count' => (int) $totalItems,
            'avg_achievement' => round((float) ($avgResult?->avg_achievement ?? 0), 2),
            // False when no plan item has an achievement yet, so the UI can say "no data" instead of 0%.
            'has_achievement' => $avgResult?->avg_achievement !== null,
            'is_team_lead' => $isTeamLead,
        ];
    }

    private function staffDashboard(User $user, int $year, int $month): Response
    {
        $employee = $user->employee;

        if (! $employee) {
            return Inertia::render('Dashboard', [
                'role' => 'staff',
                'filters' => compact('year', 'month'),
            ]);
        }

        $isTeamLead = Team::where('leader_id', $employee->id)->exists();

        // PJ with no personal plan items: show all team plans; otherwise show personal plans
        $myTeamId = $isTeamLead ? Team::where('leader_id', $employee->id)->value('id') : null;
        $hasPersonalItems = $this->achievementItems($year, $month)->where('plan_items.employee_id', $employee->id)->exists();

        $projectsBase = $this->achievementItems($year, $month)
            ->join('performance_plans', 'performance_plans.id', '=', 'plan_items.performance_plan_id')
            ->join('teams', 'teams.id', '=', 'plan_items.team_id');

        if ($isTeamLead && ! $hasPersonalItems && $myTeamId) {
            $projectsBase->where('plan_items.team_id', $myTeamId);
        } else {
            $projectsBase->where('plan_items.employee_id', $employee->id);
        }

        $projects = $projectsBase
            ->groupBy('performance_plans.id', 'performance_plans.description', 'plan_items.team_id', 'teams.name')
            ->select(
                'performance_plans.id',
                'performance_plans.description as name',
                'plan_items.team_id',
                'teams.name as team_name',
                DB::raw('AVG('.self::ACHIEVEMENT_SQL.') as achievement'),
            )
            ->get()
            ->map(fn ($plan) => [
                'id' => $plan->id,
                'team_id' => $plan->team_id,
                'name' => $plan->name,
                'team' => ['id' => $plan->team_id, 'name' => $plan->team_name],
                'achievement' => round((float) $plan->achievement, 2),
            ])
            ->values();

        $teamProjects = $isTeamLead
            ? Project::with([
                'workItems',
                'members:id,name,display_name',
                'team:id,name',
            ])
                ->where('projects.leader_id', $employee->id)
                ->where('projects.year', $year)
                ->join('teams', 'teams.id', 'projects.team_id')
                ->orderBy('teams.name')
                ->orderBy('projects.name')
                ->select('projects.*')
                ->get()
            : collect();

        // Attach how many members entered plan items per team so "Tim yang Saya Pimpin"
        // can show "N sudah input".
        if ($teamProjects->isNotEmpty()) {
            $projectTeamIds = $teamProjects->pluck('team_id')->unique()->filter();
            $monthStart = Carbon::create($year, $month, 1);
            $kipByTeam = DB::table('plan_items')
                ->where('status', '!=', 'cancelled')
                ->whereDate('date_start', '>=', $monthStart->toDateString())
                ->whereDate('date_start', '<=', $monthStart->copy()->endOfMonth()->toDateString())
                ->whereIn('team_id', $projectTeamIds)
                ->groupBy('team_id')
                ->select('team_id', DB::raw('COUNT(DISTINCT employee_id) as submitted'))
                ->get()
                ->keyBy('team_id');

            $teamProjects->each(function ($project) use ($kipByTeam) {
                $project->kinetik_submitted_count = (int) ($kipByTeam[$project->team_id]?->submitted ?? 0);
            });
        }

        $teamProgress = $this->computeTeamProgress($year, $month);

        $teams = $this->loadTeamsWithMembers($year);

        $projectLeadersByTeam = DB::table('projects')
            ->where('year', $year)
            ->whereNotNull('leader_id')
            ->get(['team_id', 'leader_id'])
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->pluck('leader_id')->unique()->values()->all())
            ->toArray();

        $topByProjects = DB::table('employees')
            ->join('project_members', 'project_members.employee_id', '=', 'employees.id')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('projects.year', $year)
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.name', 'employees.display_name')
            ->select(
                'employees.id',
                'employees.name',
                'employees.display_name',
                DB::raw('COUNT(DISTINCT project_members.project_id) as project_count'),
                DB::raw("SUM(CASE WHEN project_members.role = 'leader' THEN 1 ELSE 0 END) as leader_count"),
                DB::raw("SUM(CASE WHEN project_members.role = 'member' THEN 1 ELSE 0 END) as member_count"),
            )
            ->orderByDesc('project_count')
            ->get();

        $topByAchievement = $this->topEmployeesByAchievement($year, $month);

        return Inertia::render('Dashboard', [
            'role' => 'staff',
            'employee' => $employee->only('id', 'name', 'display_name'),
            'projects' => $projects,
            'team_projects' => $teamProjects,
            'personal_stats' => $this->personalStats($employee, $year, $month),
            'teams' => $teams,
            'team_progress' => $teamProgress,
            'project_leaders_by_team' => $projectLeadersByTeam,
            'top_employees_by_projects' => $topByProjects,
            'top_employees_by_achievement' => $topByAchievement,
            'filters' => compact('year', 'month'),
        ]);
    }

    private function headDashboard(User $user, int $year, int $month): Response
    {
        $teamProgress = $this->computeTeamProgress($year, $month);

        $projectLeadersByTeam = DB::table('projects')
            ->where('year', $year)
            ->whereNotNull('leader_id')
            ->get(['team_id', 'leader_id'])
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->pluck('leader_id')->unique()->values()->all())
            ->toArray();

        $teams = $this->loadTeamsWithMembers($year);

        $topByProjects = DB::table('employees')
            ->join('project_members', 'project_members.employee_id', '=', 'employees.id')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('projects.year', $year)
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.name', 'employees.display_name')
            ->select(
                'employees.id',
                'employees.name',
                'employees.display_name',
                DB::raw('COUNT(DISTINCT project_members.project_id) as project_count'),
                DB::raw("SUM(CASE WHEN project_members.role = 'leader' THEN 1 ELSE 0 END) as leader_count"),
                DB::raw("SUM(CASE WHEN project_members.role = 'member' THEN 1 ELSE 0 END) as member_count"),
            )
            ->orderByDesc('project_count')
            ->get();

        $topByAchievement = $this->topEmployeesByAchievement($year, $month);

        $data = [
            'role' => 'head',
            'teams' => $teams,
            'team_progress' => $teamProgress,
            'project_leaders_by_team' => $projectLeadersByTeam,
            'top_employees_by_projects' => $topByProjects,
            'top_employees_by_achievement' => $topByAchievement,
            'filters' => compact('year', 'month'),
        ];

        // Personal cards only for a head who also works in a team; a head with
        // no team would see four cards that always read 0.
        if ($user->employee?->teams()->exists()) {
            $employee = $user->employee;
            $data['employee'] = $employee->only('id', 'name', 'display_name');
            $data['personal_stats'] = $this->personalStats($employee, $year, $month);

            // Personal Kinetik plan cards (same structure as staffDashboard)
            $data['projects'] = $this->achievementItems($year, $month)
                ->join('performance_plans', 'performance_plans.id', '=', 'plan_items.performance_plan_id')
                ->join('teams', 'teams.id', '=', 'plan_items.team_id')
                ->where('plan_items.employee_id', $employee->id)
                ->groupBy('performance_plans.id', 'performance_plans.description', 'plan_items.team_id', 'teams.name')
                ->select(
                    'performance_plans.id',
                    'performance_plans.description as name',
                    'plan_items.team_id',
                    'teams.name as team_name',
                    DB::raw('AVG('.self::ACHIEVEMENT_SQL.') as achievement'),
                )
                ->get()
                ->map(fn ($plan) => [
                    'id' => $plan->id,
                    'team_id' => $plan->team_id,
                    'name' => $plan->name,
                    'team' => ['id' => $plan->team_id, 'name' => $plan->team_name],
                    'achievement' => round((float) $plan->achievement, 2),
                ])
                ->values();
        }

        return Inertia::render('Dashboard', $data);
    }

    private function adminDashboard(int $year, int $month): Response
    {
        $teamProgress = $this->computeTeamProgress($year, $month);
        $orgAvg = $this->computeOrgAvg($teamProgress);

        // 12-month trend: per-project avg → per-month avg
        $projectMonthAvgs = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_year', $year)
            ->where('projects.year', $year)
            ->groupBy('performance_reports.period_month', 'projects.id')
            ->select(
                'performance_reports.period_month',
                'projects.id as project_id',
                DB::raw('AVG(achievement_percentage) as project_avg')
            )
            ->get();

        $totalProjects = DB::table('projects')->where('year', $year)->count();

        $trend = $projectMonthAvgs
            ->groupBy('period_month')
            ->map(fn ($rows, $m) => (object) [
                'period_month' => (int) $m,
                'avg_achievement' => $totalProjects > 0
                    ? $rows->sum('project_avg') / $totalProjects
                    : 0,
            ])
            ->sortBy('period_month')
            ->values();

        $projectLeadersByTeam = DB::table('projects')
            ->where('year', $year)
            ->whereNotNull('leader_id')
            ->get(['team_id', 'leader_id'])
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->pluck('leader_id')->unique()->values()->all())
            ->toArray();

        $teams = $this->loadTeamsWithMembers($year);

        return Inertia::render('Dashboard', [
            'role' => 'admin',
            'teams' => $teams,
            'team_progress' => $teamProgress,
            'project_leaders_by_team' => $projectLeadersByTeam,
            'org_avg' => round($orgAvg ?? 0, 2),
            'trend' => $trend,
            'filters' => compact('year', 'month'),
        ]);
    }
}
