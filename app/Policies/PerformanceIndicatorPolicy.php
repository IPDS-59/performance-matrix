<?php

namespace App\Policies;

use App\Models\PerformanceIndicator;
use App\Models\Team;
use App\Models\User;

class PerformanceIndicatorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function view(User $user, PerformanceIndicator $indicator): bool
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

    public function update(User $user, PerformanceIndicator $indicator): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        return Team::where('id', $indicator->team_id)
            ->where('leader_id', $employee->id)
            ->exists();
    }

    public function delete(User $user, PerformanceIndicator $indicator): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        return Team::where('id', $indicator->team_id)
            ->where('leader_id', $employee->id)
            ->exists();
    }
}
