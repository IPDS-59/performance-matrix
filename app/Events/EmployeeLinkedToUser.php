<?php

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeLinkedToUser
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Employee $employee,
    ) {}
}
