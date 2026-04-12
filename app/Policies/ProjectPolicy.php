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
        return $user->hasPermissionTo('manage-projects');
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
