<?php

namespace App\Events;

use App\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProjectMembersUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Project $project,
    ) {}
}
