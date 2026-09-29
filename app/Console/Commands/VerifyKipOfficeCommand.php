<?php

namespace App\Console\Commands;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\OfficeGuard;
use App\Models\Employee;
use Illuminate\Console\Command;

class VerifyKipOfficeCommand extends Command
{
    protected $signature = 'kinetik:verify-office
                            {--apply : Deactivate and unlink employees who work elsewhere (default: report only)}';

    protected $description = 'Check every active employee against kipApp: only staff of the configured office (BPS Provinsi Sulawesi Tengah) stay.';

    public function handle(KipStructureSource $source): int
    {
        $guard = new OfficeGuard($source);
        $apply = (bool) $this->option('apply');
        $outside = [];
        $unknown = 0;

        $employees = Employee::with('user')
            ->where('is_active', true)
            ->whereNotNull('nip_lama')
            ->orderBy('name')
            ->get();

        foreach ($employees as $employee) {
            // Head and admin accounts are managed by hand, never removed here.
            if ($employee->user?->hasAnyRole(['head', 'admin'])) {
                continue;
            }

            $result = $guard->check($employee, fresh: true);

            if ($result === null) {
                $unknown++;
            } elseif ($result === false) {
                $outside[] = [$employee->id, $employee->name, $employee->nip_lama, $employee->kip_office];
                if ($apply) {
                    $guard->evict($employee);
                }
            }
        }

        $this->info("Checked {$employees->count()} active employees; {$unknown} unknown (kept).");

        if ($outside === []) {
            $this->info('Everyone works at the configured office.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Nama', 'NIP Lama', 'Kantor menurut kipApp'], $outside);
        $this->warn(count($outside).($apply
            ? ' employees deactivated, removed from teams and projects, logins locked.'
            : ' employees work elsewhere. Run again with --apply to remove them.'));

        return self::SUCCESS;
    }
}
