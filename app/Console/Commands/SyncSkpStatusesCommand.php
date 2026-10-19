<?php

namespace App\Console\Commands;

use App\Actions\Kinetik\SyncSkpStatusesAction;
use Illuminate\Console\Command;

class SyncSkpStatusesCommand extends Command
{
    protected $signature = 'kinetik:sync-skp-statuses';

    protected $description = 'Store where every active employee\'s yearly and quarter SKP stands in kipApp, for the atasan\'s approval page.';

    public function handle(SyncSkpStatusesAction $action): int
    {
        $result = $action->execute();
        $this->info("SKP status read for {$result['employees']} employee(s): {$result['rows']} row(s), {$result['failed']} skipped.");

        return self::SUCCESS;
    }
}
