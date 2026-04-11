<?php

namespace App\Events;

use App\Models\PerformanceReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PerformanceReportSaved
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly PerformanceReport $report,
    ) {}
}
