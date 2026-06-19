<?php

namespace App\Console\Commands;

use App\Actions\Kinetik\SyncKipPlansAction;
use App\Kinetik\Contracts\KipStructureSource;
use App\Models\Employee;
use Illuminate\Console\Command;

class SyncKipPlansCommand extends Command
{
    protected $signature = 'kinetik:sync-kip-plans
                            {--niplama=* : Limit to specific legacy NIPs (repeatable)}';

    protected $description = 'Cascade: enrich RK (performance_plans) with team + parsed IKI targets from skp/rk + skp/iki.';

    public function handle(
        KipStructureSource $source,
        SyncKipPlansAction $action,
    ): int {
        $filter = array_filter((array) $this->option('niplama'));

        $employees = Employee::query()
            ->whereNotNull('nip_lama')
            ->where('is_active', true)
            ->when(! empty($filter), fn ($q) => $q->whereIn('nip_lama', $filter))
            ->get();

        if ($employees->isEmpty()) {
            $this->warn('No active employees with nip_lama found.');

            return self::SUCCESS;
        }

        $this->info("Enriching RK for {$employees->count()} employee(s)...");

        $summary = $action->execute($source, $employees);

        $this->info("RK created: {$summary['created']}, enriched: {$summary['enriched']}.");

        return self::SUCCESS;
    }
}
