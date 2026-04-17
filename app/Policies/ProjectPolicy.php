<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Team;
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

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        // Team leads can create projects for their team.
        if (Team::where('leader_id', $employee->id)->exists()) {
            return true;
        }

        // Staff who already lead a project may create new ones.
        return Project::where('leader_id', $employee->id)->exists();
    }

    public function update(User $user, Project $project): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        // Project leader can edit their own project
        if ($employee->id === $project->leader_id) {
            return true;
        }

        // Team lead can edit any project in their team
        return Team::where('id', $project->team_id)->where('leader_id', $employee->id)->exists();
    }

    public function delete(User $user, Project $project): bool
    {
        if ($user->hasPermissionTo('manage-projects')) {
            return true;
        }

        return $user->employee !== null && $user->employee->id === $project->leader_id;
    }
}
