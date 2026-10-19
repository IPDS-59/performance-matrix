<?php

namespace App\Console\Commands;

use App\Actions\Kinetik\ImportKipActivitiesAsPlanItemsAction;
use Illuminate\Console\Command;

class ImportKipPlanItemsCommand extends Command
{
    protected $signature = 'kinetik:import-kip-plan-items';

    protected $description = 'Create plan items (Rekap Mingguan rows) for synced kipApp kegiatan that have none yet.';

    public function handle(ImportKipActivitiesAsPlanItemsAction $action): int
    {
        $this->info($action->execute().' plan items created from kipApp kegiatan.');

        return self::SUCCESS;
    }
}
