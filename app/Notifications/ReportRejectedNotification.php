<?php

namespace App\Notifications;

use App\Models\PerformanceReport;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly PerformanceReport $report,
        public readonly User $reviewer,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $workItem = $this->report->workItem;

        return [
            'type' => 'report_rejected',
            'report_id' => $this->report->id,
            'work_item_id' => $this->report->work_item_id,
            'work_item_description' => $workItem?->description,
            'period_month' => $this->report->period_month,
            'period_year' => $this->report->period_year,
            'reviewer_name' => $this->reviewer->employee?->display_name ?? $this->reviewer->name,
            'review_note' => $this->report->review_note,
            'message' => "Laporan kinerja Anda untuk {$workItem?->description} ditolak. Catatan: {$this->report->review_note}",
        ];
    }
}
