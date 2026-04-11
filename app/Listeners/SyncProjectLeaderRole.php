<?php

namespace App\Listeners;

use App\Events\ProjectMembersUpdated;

class SyncProjectLeaderRole
{
    public function handle(ProjectMembersUpdated $event): void
    {
        $project = $event->project;

        if ($project->leader_id) {
            // Ensure leader appears in project_members with role=leader
            $project->members()->syncWithoutDetaching([
                $project->leader_id => ['role' => 'leader'],
            ]);
        }
    }
}
