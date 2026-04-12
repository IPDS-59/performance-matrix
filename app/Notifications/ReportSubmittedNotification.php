<?php

namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReportSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Employee $reporter,
        public readonly int $periodMonth,
        public readonly int $periodYear,
        public readonly int $reportCount,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $monthName = $monthNames[$this->periodMonth] ?? $this->periodMonth;

        $reporterName = $this->reporter->display_name ?? $this->reporter->name;

        return [
            'type' => 'report_submitted',
            'reporter_id' => $this->reporter->id,
            'reporter_name' => $reporterName,
            'period_month' => $this->periodMonth,
            'period_year' => $this->periodYear,
            'report_count' => $this->reportCount,
            'message' => "{$reporterName} telah mengumpulkan {$this->reportCount} laporan kinerja untuk {$monthName} {$this->periodYear}.",
            'url' => route('performance.index'),
        ];
    }
}
