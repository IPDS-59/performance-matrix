<?php

namespace App\Console\Commands;

use App\Actions\Kinetik\SyncKipStructureAction;
use App\Kinetik\Contracts\KipStructureSource;
use App\Models\KipCredential;
use Illuminate\Console\Command;

class SyncKipStructureCommand extends Command
{
    protected $signature = 'kinetik:sync-kip-structure
                            {--timkerja=* : Explicit kipApp team ids to sync (repeatable); defaults to every team of the configured unit kerja}';

    protected $description = 'Mirror kipApp teams, projects, and memberships into the local master tables.';

    public function handle(
        KipStructureSource $source,
        SyncKipStructureAction $action,
    ): int {
        $timkerjaIds = array_filter((array) $this->option('timkerja'));

        if (empty($timkerjaIds)
            && KipCredential::current() === null
            && empty(config('kinetik.kip.token'))) {
            $this->error('No token configured and no --timkerja ids given.');

            return self::FAILURE;
        }

        $this->info('Syncing kipApp structure...');

        $summary = $action->execute($source, $timkerjaIds);

        $this->info("Teams: {$summary['teams']}, Projects: {$summary['projects']}, "
            ."Employees: +{$summary['employees_created']} new / {$summary['employees_updated']} updated, "
            ."Project links: {$summary['project_member_links']}, Team links: {$summary['team_member_links']}.");

        if ($summary['skipped_no_niplama'] > 0) {
            $this->warn("{$summary['skipped_no_niplama']} rows skipped (no niplama).");
        }

        return self::SUCCESS;
    }
}
