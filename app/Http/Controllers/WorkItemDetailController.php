<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\WorkItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkItemDetailController extends Controller
{
    public function show(Request $request, WorkItem $workItem): Response
    {
        $this->authorize('create', PerformanceReport::class);

        $employee = $request->user()->employee;
        abort_if(! $employee, 403, 'Akun belum terhubung ke data pegawai.');

        $workItem->load('project.team');

        $isLead = $workItem->project->leader_id === $employee->id;
        $isAssigned = $workItem->assignments()->where('employee_id', $employee->id)->exists();

        abort_unless($isLead || $isAssigned, 403);

        $year = $request->integer('year', $workItem->project->year);

        return Inertia::render('Performance/WorkItemDetail', [
            'work_item' => $this->workItemData($workItem, $employee, $isLead),
            'reports' => $isLead ? null : $this->employeeReports($workItem, $employee, $year),
            'member_reports' => $isLead ? $this->leadMemberReports($workItem, $year) : null,
            'is_lead' => $isLead,
            'year' => $year,
            'employee_id' => $employee->id,
        ]);
    }

    private function workItemData(WorkItem $workItem, Employee $employee, bool $isLead): array
    {
        $assignment = $isLead
            ? null
            : $workItem->assignments()->where('employee_id', $employee->id)->first();

        return [
            'id' => $workItem->id,
            'number' => $workItem->number,
            'description' => $workItem->description,
            'target' => (float) ($assignment?->target ?? $workItem->target),
            'target_unit' => $assignment?->target_unit ?? $workItem->target_unit,
            'project' => [
                'id' => $workItem->project->id,
                'name' => $workItem->project->name,
                'team_name' => $workItem->project->team?->name,
            ],
        ];
    }

    private function employeeReports(WorkItem $workItem, Employee $employee, int $year): array
    {
        return PerformanceReport::with([
            'reviews' => fn ($q) => $q->with('actor.employee:id,user_id,name,display_name'),
            'attachments.reviewer:id,name,display_name',
        ])
            ->where('work_item_id', $workItem->id)
            ->where('reported_by', $employee->id)
            ->where('period_year', $year)
            ->orderByDesc('period_month')
            ->get()
            ->map(fn ($r) => $this->formatReport($r))
            ->all();
    }

    private function leadMemberReports(WorkItem $workItem, int $year): array
    {
        $assignments = $workItem->assignments()->with('employee:id,name,display_name')->get();

        return $assignments->map(function ($assignment) use ($workItem, $year) {
            $reports = PerformanceReport::with([
                'reviews' => fn ($q) => $q->with('actor.employee:id,user_id,name,display_name'),
                'attachments.reviewer:id,name,display_name',
            ])
                ->where('work_item_id', $workItem->id)
                ->where('reported_by', $assignment->employee_id)
                ->where('period_year', $year)
                ->orderByDesc('period_month')
                ->get()
                ->map(fn ($r) => $this->formatReport($r))
                ->all();

            return [
                'employee' => [
                    'id' => $assignment->employee->id,
                    'name' => $assignment->employee->display_name ?? $assignment->employee->name,
                ],
                'target' => (float) $assignment->target,
                'target_unit' => $assignment->target_unit,
                'reports' => $reports,
            ];
        })->all();
    }

    private function formatReport(PerformanceReport $report): array
    {
        return [
            'id' => $report->id,
            'period_month' => $report->period_month,
            'period_year' => $report->period_year,
            'realization' => (float) $report->realization,
            'achievement_percentage' => (float) $report->achievement_percentage,
            'issues' => $report->issues,
            'solutions' => $report->solutions,
            'action_plan' => $report->action_plan,
            'approval_status' => $report->approval_status,
            'review_note' => $report->review_note,
            'reviewed_at' => $report->reviewed_at?->toISOString(),
            'reviews' => $report->reviews->map(fn ($rv) => [
                'id' => $rv->id,
                'action' => $rv->action,
                'note' => $rv->note,
                'created_at' => $rv->created_at->toISOString(),
                'actor' => $rv->actor ? [
                    'id' => $rv->actor->id,
                    'name' => $rv->actor->employee?->display_name ?? $rv->actor->employee?->name ?? $rv->actor->name,
                ] : null,
            ])->all(),
            'attachments' => $report->attachments->map(fn ($att) => [
                'id' => $att->id,
                'type' => $att->type,
                'file_name' => $att->file_name,
                'title' => $att->title,
                'url' => $att->url,
                'status' => $att->status,
                'review_note' => $att->review_note,
                'display_url' => $att->display_url,
                'reviewer' => $att->reviewer
                    ? ['name' => $att->reviewer->display_name ?? $att->reviewer->name]
                    : null,
            ])->all(),
        ];
    }
}
