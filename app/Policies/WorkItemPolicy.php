<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkItem;

class WorkItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('manage-work-items');
    }

    public function view(User $user, WorkItem $workItem): bool
    {
        return $user->hasPermissionTo('manage-work-items');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('manage-work-items');
    }

    public function update(User $user, WorkItem $workItem): bool
    {
        return $user->hasPermissionTo('manage-work-items');
    }

    public function delete(User $user, WorkItem $workItem): bool
    {
        return $user->hasPermissionTo('manage-work-items');
    }
}
