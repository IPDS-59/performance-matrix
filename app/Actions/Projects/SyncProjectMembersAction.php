<?php

namespace App\Actions\Projects;

use App\Events\ProjectMembersUpdated;
use App\Models\Project;

class SyncProjectMembersAction
{
    /**
     * @param  array<int, array{role: string}>  $memberMap  keyed by employee_id
     */
    public function execute(Project $project, array $memberMap): void
    {
        $project->members()->sync($memberMap);
        $project->refresh();

        ProjectMembersUpdated::dispatch($project);
    }
}
