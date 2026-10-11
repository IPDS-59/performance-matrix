<?php

namespace App\Console\Commands;

use App\Actions\Kinetik\SyncKipCareersAction;
use Illuminate\Console\Command;

class SyncKipCareersCommand extends Command
{
    protected $signature = 'kinetik:sync-careers';

    protected $description = 'Pull golongan, jabatan and every periodic SKP predikat from kipApp for the Angka Kredit pages.';

    public function handle(SyncKipCareersAction $action): int
    {
        $result = $action->execute();

        $this->info("Synced {$result['employees']} employees, {$result['ratings']} SKP ratings; {$result['failed']} failed.");

        return $result['failed'] > 0 && $result['employees'] === 0 ? self::FAILURE : self::SUCCESS;
    }
}
