<?php

namespace App\Actions\Employees;

use App\Events\EmployeeLinkedToUser;
use App\Models\Employee;
use App\Models\User;

class LinkEmployeeToUserAction
{
    public function execute(Employee $employee, User $user): Employee
    {
        $employee->update(['user_id' => $user->id]);
        $employee->refresh();

        EmployeeLinkedToUser::dispatch($employee);

        return $employee;
    }
}
