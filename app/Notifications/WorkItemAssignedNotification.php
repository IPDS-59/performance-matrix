<?php

namespace App\Notifications;

use App\Models\WorkItem;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WorkItemAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly WorkItem $workItem,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $project = $this->workItem->project;

        return [
            'type' => 'work_item_assigned',
            'work_item_id' => $this->workItem->id,
            'work_item_description' => $this->workItem->description,
            'project_id' => $project?->id,
            'project_name' => $project?->name,
            'team_name' => $project?->team?->name,
            'message' => "Anda ditugaskan pada rincian kegiatan \"{$this->workItem->description}\" di proyek {$project?->name}.",
            'url' => route('performance.index'),
        ];
    }
}
