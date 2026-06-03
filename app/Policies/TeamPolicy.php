<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-teams');
    }

    public function view(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('manage-teams');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage-teams');
    }

    public function update(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('manage-teams');
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->hasPermissionTo('manage-teams');
    }

    public function manageMembers(User $user, Team $team): bool
    {
        if ($user->hasPermissionTo('manage-teams')) {
            return true;
        }

        $employee = $user->employee;
        if ($employee === null) {
            return false;
        }

        return $team->leader_id === $employee->id;
    }
}
