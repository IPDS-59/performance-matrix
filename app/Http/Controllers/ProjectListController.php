<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectListController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('create', PerformanceReport::class);

        $employee = $request->user()->employee;
        abort_if(! $employee, 403, 'Akun belum terhubung ke data pegawai.');

        $year = $request->integer('year', now()->year);

        $isTeamLead = Project::where('leader_id', $employee->id)->where('year', $year)->exists();

        return Inertia::render('Performance/ProjectList', [
            'employee' => $employee->only('id', 'name', 'display_name'),
            'employee_projects' => $this->employeeProjects($employee, $year),
            'lead_projects' => $isTeamLead ? $this->leadProjects($employee, $year) : [],
            'is_team_lead' => $isTeamLead,
            'filters' => ['year' => $year],
        ]);
    }

    private function employeeProjects(Employee $employee, int $year): array
    {
        return Project::select('projects.*')->withCount([
            'workItems as assigned_items_count' => fn ($q) => $q
                ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id)),
            'workItems as submitted_items_count' => fn ($q) => $q
                ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
                ->whereHas('performanceReports', fn ($q) => $q
                    ->where('reported_by', $employee->id)
                    ->where('period_year', $year)),
            'workItems as pending_review_count' => fn ($q) => $q
                ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
                ->whereHas('performanceReports', fn ($q) => $q
                    ->where('reported_by', $employee->id)
                    ->where('period_year', $year)
                    ->where('approval_status', 'pending')),
            'workItems as rejected_count' => fn ($q) => $q
                ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
                ->whereHas('performanceReports', fn ($q) => $q
                    ->where('reported_by', $employee->id)
                    ->where('period_year', $year)
                    ->where('approval_status', 'rejected')),
        ])
            ->whereHas('members', fn ($q) => $q->where('employees.id', $employee->id))
            ->where('year', $year)
            ->with('team:id,name')
            ->join('teams', 'teams.id', '=', 'projects.team_id')
            ->orderBy('teams.name')
            ->orderBy('projects.name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'year' => $p->year,
                'leader_id' => $p->leader_id,
                'team' => $p->team ? ['id' => $p->team->id, 'name' => $p->team->name] : null,
                'assigned_items_count' => $p->assigned_items_count,
                'submitted_items_count' => $p->submitted_items_count,
                'pending_review_count' => $p->pending_review_count,
                'rejected_count' => $p->rejected_count,
            ])
            ->all();
    }

    private function leadProjects(Employee $employee, int $year): array
    {
        return Project::select('projects.*')->withCount([
            'workItems as work_items_count',
            'members as members_count',
            'performanceReports as pending_reviews_count' => fn ($q) => $q
                ->where('period_year', $year)
                ->where('approval_status', 'pending'),
            'performanceReports as total_reports_count' => fn ($q) => $q
                ->where('period_year', $year),
        ])
            ->where('projects.leader_id', $employee->id)
            ->where('projects.year', $year)
            ->with('team:id,name')
            ->join('teams', 'teams.id', '=', 'projects.team_id')
            ->orderBy('teams.name')
            ->orderBy('projects.name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'year' => $p->year,
                'team' => $p->team ? ['id' => $p->team->id, 'name' => $p->team->name] : null,
                'work_items_count' => $p->work_items_count,
                'members_count' => $p->members_count,
                'pending_reviews_count' => $p->pending_reviews_count,
                'total_reports_count' => $p->total_reports_count,
            ])
            ->all();
    }
}
