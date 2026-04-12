<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\Project;
use App\Models\WorkItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectDetailController extends Controller
{
    public function show(Request $request, Project $project): Response
    {
        $this->authorize('create', PerformanceReport::class);

        $employee = $request->user()->employee;
        abort_if(! $employee, 403, 'Akun belum terhubung ke data pegawai.');

        $isLead = $project->leader_id === $employee->id;
        $isMember = $project->members()->where('employees.id', $employee->id)->exists();

        abort_unless($isLead || $isMember, 403);

        $year = $request->integer('year', $project->year);

        return Inertia::render('Performance/ProjectDetail', [
            'project' => $this->projectData($project),
            'work_items' => $isLead
                ? $this->leadWorkItems($project, $year)
                : $this->employeeWorkItems($project, $employee, $year),
            'is_lead' => $isLead,
            'year' => $year,
        ]);
    }

    private function projectData(Project $project): array
    {
        $project->loadMissing('team:id,name', 'leader:id,name,display_name');

        return [
            'id' => $project->id,
            'name' => $project->name,
            'year' => $project->year,
            'leader_id' => $project->leader_id,
            'team' => $project->team ? ['id' => $project->team->id, 'name' => $project->team->name] : null,
            'leader' => $project->leader
                ? ['id' => $project->leader->id, 'name' => $project->leader->display_name ?? $project->leader->name]
                : null,
        ];
    }

    private function employeeWorkItems(Project $project, Employee $employee, int $year): array
    {
        return WorkItem::with([
            'assignments' => fn ($q) => $q
                ->where('employee_id', $employee->id)
                ->select(['work_item_id', 'employee_id', 'target', 'target_unit']),
            'performanceReports' => fn ($q) => $q
                ->where('reported_by', $employee->id)
                ->where('period_year', $year)
                ->select(['id', 'work_item_id', 'period_month', 'realization', 'approval_status']),
        ])
            ->whereHas('assignments', fn ($q) => $q->where('employee_id', $employee->id))
            ->where('project_id', $project->id)
            ->orderBy('number')
            ->get()
            ->map(function (WorkItem $wi) use ($year) {
                $assignment = $wi->assignments->first();
                $reports = $wi->performanceReports;
                $target = (float) ($assignment?->target ?? $wi->target);
                $yearRealization = (float) $reports->sum('realization');
                $yearPct = $target > 0 ? min(100, round($yearRealization / $target * 100, 1)) : 0;

                return [
                    'id' => $wi->id,
                    'number' => $wi->number,
                    'description' => $wi->description,
                    'target' => $target,
                    'target_unit' => $assignment?->target_unit ?? $wi->target_unit,
                    'year_realization' => $yearRealization,
                    'year_pct' => $yearPct,
                    'report_count' => $reports->count(),
                    'has_pending' => $reports->contains('approval_status', 'pending'),
                    'has_rejected' => $reports->contains('approval_status', 'rejected'),
                    'all_approved' => $reports->isNotEmpty() && $reports->every(fn ($r) => $r->approval_status === 'approved'),
                ];
            })
            ->all();
    }

    private function leadWorkItems(Project $project, int $year): array
    {
        return WorkItem::select('work_items.*')
            ->withCount([
                'performanceReports as pending_count' => fn ($q) => $q
                    ->where('period_year', $year)->where('approval_status', 'pending'),
                'performanceReports as approved_count' => fn ($q) => $q
                    ->where('period_year', $year)->where('approval_status', 'approved'),
                'performanceReports as rejected_count' => fn ($q) => $q
                    ->where('period_year', $year)->where('approval_status', 'rejected'),
                'performanceReports as total_report_count' => fn ($q) => $q
                    ->where('period_year', $year),
            ])
            ->with([
                'assignments' => fn ($q) => $q
                    ->with('employee:id,name,display_name')
                    ->select(['work_item_id', 'employee_id', 'target', 'target_unit']),
            ])
            ->where('project_id', $project->id)
            ->orderBy('number')
            ->get()
            ->map(fn (WorkItem $wi) => [
                'id' => $wi->id,
                'number' => $wi->number,
                'description' => $wi->description,
                'target' => (float) $wi->target,
                'target_unit' => $wi->target_unit,
                'assigned_members' => $wi->assignments->map(fn ($a) => [
                    'employee_id' => $a->employee_id,
                    'name' => $a->employee?->display_name ?? $a->employee?->name ?? '—',
                    'target' => (float) $a->target,
                    'target_unit' => $a->target_unit,
                ])->values()->all(),
                'pending_count' => $wi->pending_count,
                'approved_count' => $wi->approved_count,
                'rejected_count' => $wi->rejected_count,
                'total_report_count' => $wi->total_report_count,
            ])
            ->all();
    }
}
