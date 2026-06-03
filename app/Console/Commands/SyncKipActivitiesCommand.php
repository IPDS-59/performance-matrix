<?php

namespace App\Console\Commands;

use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Contracts\KipActivitySource;
use App\Models\Employee;
use Illuminate\Console\Command;

class SyncKipActivitiesCommand extends Command
{
    protected $signature = 'kinetik:sync-kip-activities
                            {--niplama=* : Limit sync to specific legacy NIPs (repeatable)}';

    protected $description = 'Fetch unsent kipApp activities and upsert them into kip_activities.';

    public function handle(
        KipActivitySource $source,
        SyncKipActivitiesAction $action,
    ): int {
        $nipLamaFilter = array_filter((array) $this->option('niplama'));

        $employees = Employee::query()
            ->whereNotNull('nip_lama')
            ->where('is_active', true)
            ->when(
                ! empty($nipLamaFilter),
                fn ($q) => $q->whereIn('nip_lama', $nipLamaFilter),
            )
            ->get();

        if ($employees->isEmpty()) {
            $this->warn('No active employees with nip_lama found.');

            return self::SUCCESS;
        }

        $this->info("Syncing activities for {$employees->count()} employee(s)...");

        $upserted = $action->execute($source, $employees);

        $this->info("Done. {$upserted} activity row(s) upserted.");

        return self::SUCCESS;
    }
}
