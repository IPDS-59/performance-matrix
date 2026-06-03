<?php

namespace App\Policies;

use App\Models\PerformancePlan;
use App\Models\Team;
use App\Models\User;

class PerformancePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function view(User $user, PerformancePlan $plan): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function create(User $user): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        return Team::where('leader_id', $employee->id)->exists();
    }

    public function update(User $user, PerformancePlan $plan): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        $plan->loadMissing('project');

        return Team::where('id', $plan->project->team_id)
            ->where('leader_id', $employee->id)
            ->exists();
    }

    public function delete(User $user, PerformancePlan $plan): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        $plan->loadMissing('project');

        return Team::where('id', $plan->project->team_id)
            ->where('leader_id', $employee->id)
            ->exists();
    }
}
