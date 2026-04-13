<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeReportController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', PerformanceReport::class);

        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);

        // Top 10 employees by average achievement for the given period.
        // Path: performance_reports → work_items → projects → project_members → employees
        $top10 = DB::table('employees')
            ->join('project_members', 'employees.id', '=', 'project_members.employee_id')
            ->join('projects', 'project_members.project_id', '=', 'projects.id')
            ->join('work_items', 'projects.id', '=', 'work_items.project_id')
            ->join('performance_reports', 'work_items.id', '=', 'performance_reports.work_item_id')
            ->whereColumn('performance_reports.reported_by', 'employees.id')
            ->where('performance_reports.period_year', $year)
            ->where('performance_reports.period_month', $month)
            ->groupBy('employees.id', 'employees.name', 'employees.display_name')
            ->orderByDesc('avg_achievement')
            ->limit(10)
            ->select([
                'employees.id',
                'employees.name',
                'employees.display_name',
                DB::raw('AVG(performance_reports.achievement_percentage) as avg_achievement'),
            ])
            ->get();

        // Top 10 employees by total project count for the selected year.
        $top10ByProjects = DB::table('employees')
            ->join('project_members', 'employees.id', '=', 'project_members.employee_id')
            ->join('projects', 'project_members.project_id', '=', 'projects.id')
            ->where('employees.is_active', true)
            ->where('projects.year', $year)
            ->groupBy('employees.id', 'employees.name', 'employees.display_name')
            ->orderByDesc('total_projects')
            ->limit(10)
            ->select([
                'employees.id',
                'employees.name',
                'employees.display_name',
                DB::raw('COUNT(DISTINCT project_members.project_id) as total_projects'),
                DB::raw("SUM(CASE WHEN project_members.role = 'leader' THEN 1 ELSE 0 END) as leader_count"),
                DB::raw("SUM(CASE WHEN project_members.role = 'member' THEN 1 ELSE 0 END) as member_count"),
            ])
            ->get();

        // Subquery: avg achievement per employee for the current filter period.
        $achievementSub = DB::table('employees as e2')
            ->join('project_members as pm2', 'e2.id', '=', 'pm2.employee_id')
            ->join('projects as p2', 'pm2.project_id', '=', 'p2.id')
            ->join('work_items as wi2', 'p2.id', '=', 'wi2.project_id')
            ->join('performance_reports as pr2', 'wi2.id', '=', 'pr2.work_item_id')
            ->whereColumn('pr2.reported_by', 'e2.id')
            ->where('pr2.period_year', $year)
            ->where('pr2.period_month', $month)
            ->groupBy('e2.id')
            ->select([
                'e2.id as employee_id',
                DB::raw('AVG(pr2.achievement_percentage) as avg_achievement'),
            ]);

        // All active employees with team, project counts, and period achievement.
        $employees = Employee::with('team:id,name')
            ->leftJoinSub($achievementSub, 'achievement_stats', fn ($join) => $join->on('employees.id', '=', 'achievement_stats.employee_id'))
            ->where('employees.is_active', true)
            ->orderBy('employees.name')
            ->select([
                'employees.*',
                'achievement_stats.avg_achievement',
                DB::raw('(SELECT COUNT(*) FROM project_members pm WHERE pm.employee_id = employees.id) as total_projects'),
                DB::raw("(SELECT COUNT(*) FROM project_members pm WHERE pm.employee_id = employees.id AND pm.role = 'leader') as leader_count"),
                DB::raw("(SELECT COUNT(*) FROM project_members pm WHERE pm.employee_id = employees.id AND pm.role = 'member') as member_count"),
            ])
            ->get();

        return Inertia::render('Laporan/Pegawai', [
            'top10' => $top10,
            'top10ByProjects' => $top10ByProjects,
            'employees' => $employees,
            'filters' => compact('year', 'month'),
        ]);
    }
}
