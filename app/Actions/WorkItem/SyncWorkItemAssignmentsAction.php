<?php

namespace App\Actions\WorkItem;

use App\Models\Project;
use App\Models\WorkItem;
use App\Models\WorkItemAssignment;

class SyncWorkItemAssignmentsAction
{
    /**
     * @param  array{assign_to: 'all'|'specific', target: int, target_unit: string, assignments?: array<array{employee_id: int, target: int, target_unit: string}>}  $data
     */
    public function execute(WorkItem $workItem, Project $project, array $data): void
    {
        if ($data['assign_to'] === 'all') {
            $this->syncAll($workItem, $project, $data['target'], $data['target_unit']);
        } else {
            $this->syncSpecific($workItem, $data['assignments'] ?? []);
        }
    }

    private function syncAll(WorkItem $workItem, Project $project, int $target, string $targetUnit): void
    {
        $memberIds = $project->members()->pluck('employees.id');

        WorkItemAssignment::where('work_item_id', $workItem->id)
            ->whereNotIn('employee_id', $memberIds)
            ->delete();

        foreach ($memberIds as $employeeId) {
            WorkItemAssignment::updateOrCreate(
                ['work_item_id' => $workItem->id, 'employee_id' => $employeeId],
                ['target' => $target, 'target_unit' => $targetUnit]
            );
        }
    }

    private function syncSpecific(WorkItem $workItem, array $rows): void
    {
        $incoming = collect($rows);
        $incomingIds = $incoming->pluck('employee_id');

        WorkItemAssignment::where('work_item_id', $workItem->id)
            ->whereNotIn('employee_id', $incomingIds)
            ->delete();

        foreach ($incoming as $row) {
            WorkItemAssignment::updateOrCreate(
                ['work_item_id' => $workItem->id, 'employee_id' => $row['employee_id']],
                ['target' => $row['target'], 'target_unit' => $row['target_unit']]
            );
        }
    }
}
