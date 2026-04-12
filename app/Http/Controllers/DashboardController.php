<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        $this->authorize('viewAny', PerformanceReport::class);

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

        return Inertia::render('Matrix/Index', compact(
            'employees', 'projects', 'assignments', 'progress', 'teams', 'year', 'month', 'teamId'
        ));
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

        $avgAchievement = DB::table('performance_reports')
            ->whereIn('work_item_id', $workItemIds)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->avg('achievement_percentage');

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
                ->where('leader_id', $employee->id)
                ->where('year', $year)
                ->join('teams', 'teams.id', 'projects.team_id')
                ->orderBy('teams.name')
                ->orderBy('projects.name')
                ->select('projects.*')
                ->get()
            : collect();

        $cacheKey = "team_progress:{$year}:{$month}";
        $teamProgress = Cache::get($cacheKey, collect());

        $teams = Team::with(['employees' => fn ($q) => $q->select('employees.id', 'name', 'display_name', 'team_id')])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $projectLeaderIds = DB::table('projects')
            ->where('year', $year)
            ->whereNotNull('leader_id')
            ->pluck('leader_id')
            ->unique()
            ->values();

        $topByProjects = DB::table('employees')
            ->join('project_members', 'project_members.employee_id', '=', 'employees.id')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('projects.year', $year)
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.name', 'employees.display_name')
            ->select('employees.id', 'employees.name', 'employees.display_name', DB::raw('COUNT(DISTINCT project_members.project_id) as project_count'))
            ->orderByDesc('project_count')
            ->limit(10)
            ->get();

        $topByAchievement = DB::table('employees')
            ->join('performance_reports', 'performance_reports.reported_by', '=', 'employees.id')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->where('projects.year', $year)
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.name', 'employees.display_name')
            ->select('employees.id', 'employees.name', 'employees.display_name', DB::raw('AVG(performance_reports.achievement_percentage) as avg_achievement'))
            ->orderByDesc('avg_achievement')
            ->limit(10)
            ->get();

        return Inertia::render('Dashboard', [
            'role' => 'staff',
            'employee' => $employee->only('id', 'name', 'display_name'),
            'projects' => $projects,
            'team_projects' => $teamProjects,
            'personal_stats' => $this->personalStats($employee, $year, $month),
            'teams' => $teams,
            'team_progress' => $teamProgress,
            'project_leader_ids' => $projectLeaderIds,
            'top_employees_by_projects' => $topByProjects,
            'top_employees_by_achievement' => $topByAchievement,
            'filters' => compact('year', 'month'),
        ]);
    }

    private function headDashboard($user, int $year, int $month): Response
    {
        $teamProgress = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_month', $month)
            ->where('performance_reports.period_year', $year)
            ->groupBy('projects.team_id')
            ->select(
                'projects.team_id',
                DB::raw('AVG(performance_reports.achievement_percentage) as avg_achievement'),
                DB::raw('COUNT(performance_reports.id) as report_count')
            )
            ->get()
            ->keyBy('team_id');

        $projectLeaderIds = DB::table('projects')
            ->where('year', $year)
            ->whereNotNull('leader_id')
            ->pluck('leader_id')
            ->unique()
            ->values();

        $teams = Team::with(['employees' => fn ($q) => $q->select('employees.id', 'name', 'display_name', 'team_id')])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $topByProjects = DB::table('employees')
            ->join('project_members', 'project_members.employee_id', '=', 'employees.id')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('projects.year', $year)
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.name', 'employees.display_name')
            ->select('employees.id', 'employees.name', 'employees.display_name', DB::raw('COUNT(DISTINCT project_members.project_id) as project_count'))
            ->orderByDesc('project_count')
            ->limit(10)
            ->get();

        $topByAchievement = DB::table('employees')
            ->join('performance_reports', 'performance_reports.reported_by', '=', 'employees.id')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->where('projects.year', $year)
            ->where('employees.is_active', true)
            ->groupBy('employees.id', 'employees.name', 'employees.display_name')
            ->select('employees.id', 'employees.name', 'employees.display_name', DB::raw('AVG(performance_reports.achievement_percentage) as avg_achievement'))
            ->orderByDesc('avg_achievement')
            ->limit(10)
            ->get();

        $data = [
            'role' => 'head',
            'teams' => $teams,
            'team_progress' => $teamProgress,
            'project_leader_ids' => $projectLeaderIds,
            'top_employees_by_projects' => $topByProjects,
            'top_employees_by_achievement' => $topByAchievement,
            'filters' => compact('year', 'month'),
        ];

        if ($user->employee) {
            $data['personal_stats'] = $this->personalStats($user->employee, $year, $month);
        }

        return Inertia::render('Dashboard', $data);
    }

    private function adminDashboard(int $year, int $month): Response
    {
        $cacheKey = "team_progress:{$year}:{$month}";
        $teamProgress = Cache::get($cacheKey, collect());

        $orgAvg = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->where('projects.year', $year)
            ->avg('performance_reports.achievement_percentage');

        // 12-month trend
        $trend = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->join('projects', 'projects.id', '=', 'work_items.project_id')
            ->where('performance_reports.period_year', $year)
            ->where('projects.year', $year)
            ->groupBy('performance_reports.period_month')
            ->orderBy('performance_reports.period_month')
            ->select('performance_reports.period_month', DB::raw('AVG(achievement_percentage) as avg_achievement'))
            ->get();

        $projectLeaderIds = DB::table('projects')
            ->where('year', $year)
            ->whereNotNull('leader_id')
            ->pluck('leader_id')
            ->unique()
            ->values();

        $teams = Team::with(['employees' => fn ($q) => $q->select('employees.id', 'name', 'display_name', 'team_id')])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Dashboard', [
            'role' => 'admin',
            'teams' => $teams,
            'team_progress' => $teamProgress,
            'project_leader_ids' => $projectLeaderIds,
            'org_avg' => round($orgAvg ?? 0, 2),
            'trend' => $trend,
            'filters' => compact('year', 'month'),
        ]);
    }
}
