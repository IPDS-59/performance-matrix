<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermissionTo('manage-projects') || $user->hasRole(['head', 'staff']);
    }

    public function create(User $user): bool
    {
        if ($user->hasPermissionTo('manage-projects') || $user->hasPermissionTo('create-project')) {
            return true;
        }

        // Staff who already lead a project may create new ones for their team.
        $employee = $user->employee;

        return $employee !== null && Project::where('leader_id', $employee->id)->exists();
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        return $user->employee !== null && $user->employee->id === $project->leader_id;
    }

    public function delete(User $user, Project $project): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        return $user->employee !== null && $user->employee->id === $project->leader_id;
    }
}
