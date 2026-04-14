<?php

namespace App\Listeners;

use App\Events\EmployeeLinkedToUser;

class AssignStaffRole
{
    public function handle(EmployeeLinkedToUser $event): void
    {
        $employee = $event->employee;

        if ($employee->user_id && $employee->user) {
            $user = $employee->user;
            if (! $user->hasRole('staff') && ! $user->hasRole('admin') && ! $user->hasRole('head')) {
                $user->assignRole('staff');
                $user->update(['role' => 'staff']);
            }
        }
    }
}
