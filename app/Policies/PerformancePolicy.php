<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\PerformanceReport;
use App\Models\User;
use App\Models\WorkItem;

class PerformancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view-matrix') || $user->hasPermissionTo('view-reports');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('enter-performance');
    }

    /**
     * Staff may only update reports for work items within projects they are assigned to.
     */
    public function update(User $user, PerformanceReport $report): bool
    {
        if (! $user->hasPermissionTo('enter-performance')) {
            return false;
        }

        $employee = $user->employee;
        if (! $employee) {
            return false;
        }

        return $this->employeeOwnsWorkItem($employee, $report->workItem);
    }

    public function store(User $user, WorkItem $workItem): bool
    {
        if (! $user->hasPermissionTo('enter-performance')) {
            return false;
        }

        $employee = $user->employee;
        if (! $employee) {
            return false;
        }

        return $this->employeeOwnsWorkItem($employee, $workItem);
    }

    public function delete(User $user, PerformanceReport $report): bool
    {
        if (! $user->hasPermissionTo('enter-performance')) {
            return false;
        }

        $employee = $user->employee;

        return $employee && (int) $report->reported_by === $employee->id && ! $report->isApproved();
    }

    private function employeeOwnsWorkItem(Employee $employee, WorkItem $workItem): bool
    {
        return $workItem->project->members()->where('employees.id', $employee->id)->exists();
    }
}
