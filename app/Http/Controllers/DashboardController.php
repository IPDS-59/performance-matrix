<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Team;
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

    /**
     * Compute team achievement as: avg of per-project averages.
     *
     * A flat AVG across all reports inflates teams whose one active project
     * has many 100% reports. By averaging per-project first we ensure each
     * project contributes equally. Projects with zero reports in the period
     * count as 0% so a team with 10 projects and only 1 reporting 100%
     * won't show 100%.
     */
    private function computeTeamProgress(int $year, int $month): Collection
    {
        // Per-project averages for the requested period
        $projectAvgs = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->where('projects.year', $year)
            ->groupBy('projects.team_id', 'projects.id')
            ->select(
                'projects.team_id',
                'projects.id as project_id',
                DB::raw('AVG(performance_reports.achievement_percentage) as project_avg'),
                DB::raw('COUNT(performance_reports.id) as report_count')
            )
            ->get();

        // Total projects per team (including those with no reports)
        $totalProjectsByTeam = DB::table('projects')
            ->where('year', $year)
            ->groupBy('team_id')
            ->select('team_id', DB::raw('COUNT(*) as total'))
            ->get()
            ->keyBy('team_id');

        // Roll up: team avg = sum(project avgs) / total projects in team
        return $projectAvgs
            ->groupBy('team_id')
            ->map(function ($rows, $teamId) use ($totalProjectsByTeam) {
                $sumAvg = $rows->sum('project_avg');
                $totalProjects = $totalProjectsByTeam[$teamId]->total ?? $rows->count();
                $reportCount = $rows->sum('report_count');

                return (object) [
                    'team_id' => (int) $teamId,
                    'avg_achievement' => $totalProjects > 0 ? $sumAvg / $totalProjects : 0,
                    'report_count' => $reportCount,
                ];
            })
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
     * Top employees ranked by achievement: per-project avg ÷ total assigned projects.
     *
     * An employee assigned to 5 projects who only reported on 1 at 100%
     * gets 20%, not 100%.
     */
    private function topEmployeesByAchievement(int $year, int $month, int $limit = 10): Collection
    {
        // Per-employee per-project averages
        $perProject = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->where('projects.year', $year)
            ->groupBy('performance_reports.reported_by', 'projects.id')
            ->select(
                'performance_reports.reported_by',
                'projects.id as project_id',
                DB::raw('AVG(performance_reports.achievement_percentage) as project_avg')
            )
            ->get();

        // Total assigned projects per employee
        $assignedCounts = DB::table('project_members')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('projects.year', $year)
            ->groupBy('project_members.employee_id')
            ->select('project_members.employee_id', DB::raw('COUNT(DISTINCT project_members.project_id) as total'))
            ->get()
            ->keyBy('employee_id');

        $employees = DB::table('employees')
            ->where('is_active', true)
            ->get(['id', 'name', 'display_name'])
            ->keyBy('id');

        return $perProject
            ->groupBy('reported_by')
            ->map(function ($rows, $employeeId) use ($assignedCounts, $employees) {
                $emp = $employees[$employeeId] ?? null;
                if (! $emp) {
                    return null;
                }
                $total = $assignedCounts[$employeeId]->total ?? $rows->count();

                return (object) [
                    'id' => (int) $employeeId,
                    'name' => $emp->name,
                    'display_name' => $emp->display_name,
                    'avg_achievement' => $total > 0 ? $rows->sum('project_avg') / $total : 0,
                ];
            })
            ->filter()
            ->sortByDesc('avg_achievement')
            ->take($limit)
            ->values();
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
        $projectIds = DB::table('project_members')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('project_members.employee_id', $employee->id)
            ->where('projects.year', $year)
            ->pluck('project_members.project_id')
            ->unique()
            ->values();

        $teamIds = DB::table('projects')
            ->whereIn('id', $projectIds)
            ->pluck('team_id')
            ->unique()
            ->values();

        $workItemIds = DB::table('work_items')
            ->whereIn('project_id', $projectIds)
            ->pluck('id');

        // Per-project avg → employee avg (projects without reports count as 0%)
        $projectAvgs = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->whereIn('work_items.project_id', $projectIds)
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->groupBy('work_items.project_id')
            ->select(DB::raw('AVG(performance_reports.achievement_percentage) as project_avg'))
            ->get();

        $totalAssigned = $projectIds->count();
        $avgAchievement = $totalAssigned > 0
            ? $projectAvgs->sum('project_avg') / $totalAssigned
            : 0;

        $isTeamLead = Project::where('leader_id', $employee->id)
            ->where('year', $year)
            ->exists();

        return [
            'teams_count' => $teamIds->count(),
            'projects_count' => $projectIds->count(),
            'items_count' => $workItemIds->count(),
            'avg_achievement' => round($avgAchievement ?? 0, 2),
            'is_team_lead' => $isTeamLead,
        ];
    }

    private function staffDashboard($user, int $year, int $month): Response
    {
        $employee = $user->employee;

        if (! $employee) {
            return Inertia::render('Dashboard', [
                'role' => 'staff',
                'filters' => compact('year', 'month'),
            ]);
        }

        $isTeamLead = Project::where('leader_id', $employee->id)->where('year', $year)->exists();

        $projects = Project::with([
            'workItems.performanceReports' => fn ($q) => $q->where('period_year', $year)->where('period_month', $month),
            'team:id,name',
        ])
            ->whereHas('members', fn ($q) => $q->where('employees.id', $employee->id))
            ->where('year', $year)
            ->join('teams', 'teams.id', '=', 'projects.team_id')
            ->orderBy('teams.name')
            ->orderBy('projects.name')
            ->select('projects.*')
            ->get();

        $teamProjects = $isTeamLead
            ? Project::with([
                'workItems' => fn ($q) => $q->with([
                    'performanceReports' => fn ($q) => $q
                        ->where('period_year', $year)
                        ->where('period_month', $month)
                        ->with('reporter:id,name,display_name'),
                ]),
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

    private function headDashboard($user, int $year, int $month): Response
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

        if ($user->employee) {
            $employee = $user->employee;
            $data['employee'] = $employee->only('id', 'name', 'display_name');
            $data['personal_stats'] = $this->personalStats($employee, $year, $month);

            // Personal projects with work items (same as staff view)
            $data['projects'] = Project::with([
                'workItems' => fn ($q) => $q
                    ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
                    ->with([
                        'assignments' => fn ($q) => $q->where('employee_id', $employee->id),
                        'performanceReports' => fn ($q) => $q
                            ->where('period_year', $year)
                            ->where('reported_by', $employee->id)
                            ->with('attachments'),
                    ]),
                'team:id,name',
            ])
                ->whereHas('members', fn ($q) => $q->where('employees.id', $employee->id))
                ->where('year', $year)
                ->join('teams', 'teams.id', '=', 'projects.team_id')
                ->orderBy('teams.name')
                ->orderBy('projects.name')
                ->select('projects.*')
                ->get()
                ->map(function ($project) {
                    $project->workItems->transform(function ($wi) {
                        $assignment = $wi->assignments->first();
                        $wi->target = $assignment?->target ?? $wi->target;
                        $wi->target_unit = $assignment?->target_unit ?? $wi->target_unit;
                        unset($wi->assignments);

                        return $wi;
                    });

                    return $project;
                });
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
