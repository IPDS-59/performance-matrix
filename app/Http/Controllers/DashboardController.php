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
            return $this->headDashboard($year, $month);
        }

        return $this->adminDashboard($year, $month);
    }

    public function matrix(Request $request): Response
    {
        $this->authorize('viewAny', PerformanceReport::class);

        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $teamId = $request->integer('team_id');

        $employees = Employee::with('team:id,name')
            ->where('is_active', true)
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->orderBy('name')
            ->get(['id', 'name', 'display_name', 'team_id']);

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

        // Progress: avg achievement per project for selected period
        $progress = DB::table('performance_reports')
            ->join('work_items', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->whereIn('work_items.project_id', $projects->pluck('id'))
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->groupBy('work_items.project_id')
            ->select('work_items.project_id', DB::raw('AVG(achievement_percentage) as avg_achievement'))
            ->pluck('avg_achievement', 'project_id');

        $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Matrix/Index', compact(
            'employees', 'projects', 'assignments', 'progress', 'teams', 'year', 'month', 'teamId'
        ));
    }

    private function staffDashboard($user, int $year, int $month): Response
    {
        $employee = $user->employee;

        if (! $employee) {
            return Inertia::render('Dashboard', ['role' => 'staff', 'data' => null]);
        }

        $projects = Project::with([
            'workItems.performanceReports' => fn ($q) => $q->where('period_year', $year)->where('period_month', $month),
        ])
            ->whereHas('members', fn ($q) => $q->where('employees.id', $employee->id))
            ->where('year', $year)
            ->get();

        return Inertia::render('Dashboard', [
            'role' => 'staff',
            'employee' => $employee->only('id', 'name', 'display_name'),
            'projects' => $projects,
            'filters' => compact('year', 'month'),
        ]);
    }

    private function headDashboard(int $year, int $month): Response
    {
        $cacheKey = "team_progress:{$year}:{$month}";
        $teamProgress = Cache::get($cacheKey, collect());

        $teams = Team::orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Dashboard', [
            'role' => 'head',
            'teams' => $teams,
            'team_progress' => $teamProgress,
            'filters' => compact('year', 'month'),
        ]);
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

        $teams = Team::orderBy('name')->get(['id', 'name', 'code']);

        return Inertia::render('Dashboard', [
            'role' => 'admin',
            'teams' => $teams,
            'team_progress' => $teamProgress,
            'org_avg' => round($orgAvg ?? 0, 2),
            'trend' => $trend,
            'filters' => compact('year', 'month'),
        ]);
    }
}
