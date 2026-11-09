<?php

namespace App\Kinetik\Contracts;

/**
 * A kegiatan source that can tell whether its last fetchActivities() returned every kegiatan of the employee in the
 * configured period. Only then may a kegiatan that did not come back be taken as deleted in kipApp.
 */
interface ReportsCompleteness
{
    public function lastFetchWasComplete(): bool;
}
