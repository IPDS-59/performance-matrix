<?php

namespace App\Console\Commands;

use App\Actions\Kinetik\RemindMonthlyCapaianAction;
use Illuminate\Console\Command;

class RemindCapaianCommand extends Command
{
    protected $signature = 'kinetik:remind-capaian {--month= : YYYY-MM to remind about (default: last month)}';

    protected $description = 'Notify members (and their PJ) about kegiatan of a month that still lack a target or capaian.';

    public function handle(RemindMonthlyCapaianAction $remind): int
    {
        $sent = $remind->execute(null, $this->option('month') ?: null);
        $this->info("Reminders sent: {$sent['members']} members, {$sent['pj']} PJ.");

        return self::SUCCESS;
    }
}
