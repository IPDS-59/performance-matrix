<?php

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PerformanceBatchSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Employee $reporter,
        public readonly int $periodMonth,
        public readonly int $periodYear,
        public readonly array $reportIds,
        public readonly array $workItemIds = [],
    ) {}
}
