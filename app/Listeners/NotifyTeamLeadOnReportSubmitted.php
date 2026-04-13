<?php

namespace App\Listeners;

use App\Events\PerformanceBatchSubmitted;
use App\Models\PerformanceReport;
use App\Models\Project;
use App\Notifications\ReportSubmittedNotification;

class NotifyTeamLeadOnReportSubmitted
{
    public function handle(PerformanceBatchSubmitted $event): void
    {
        // Find all projects where this employee is a member this year
        $projects = Project::with('leader.user')
            ->whereHas('members', fn ($q) => $q->where('employees.id', $event->reporter->id))
            ->where('year', $event->periodYear)
            ->whereNotNull('leader_id')
            ->get();

        $notified = [];

        foreach ($projects as $project) {
            if (! $project->leader?->user) {
                continue;
            }

            // Don't notify if the leader IS the reporter
            if ($project->leader_id === $event->reporter->id) {
                continue;
            }

            // Only notify each leader once per batch
            if (in_array((int) $project->leader_id, $notified, true)) {
                continue;
            }

            // Find the work item from this batch that belongs to this project (usually one)
            $workItemId = PerformanceReport::whereIn('id', $event->reportIds)
                ->whereHas('workItem', fn ($q) => $q->where('project_id', $project->id))
                ->value('work_item_id');

            $project->leader->user->notify(new ReportSubmittedNotification(
                $event->reporter,
                $event->periodMonth,
                $event->periodYear,
                count($event->reportIds),
                $project,
                $workItemId,
            ));

            $notified[] = (int) $project->leader_id;
        }
    }
}
