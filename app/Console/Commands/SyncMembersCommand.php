<?php

namespace App\Console\Commands;

use App\Jobs\SyncMemberDataJob;
use App\Models\Employee;
use App\Models\MemberSyncWait;
use Illuminate\Console\Command;

class SyncMembersCommand extends Command
{
    protected $signature = 'kinetik:sync-members {--retry-sso : Only the members that waited for BPS SSO}';

    protected $description = 'Queue every member\'s own kipApp sync. Members whose login ran out wait until they sign in again.';

    public function handle(): int
    {
        $waiting = MemberSyncWait::pluck('reason', 'employee_id');

        $employees = Employee::query()
            ->where('is_active', true)->whereNotNull('nip_lama')
            ->whereHas('user.memberKipCredential')
            ->when($this->option('retry-sso'), fn ($q) => $q->whereIn('id', $waiting->filter(fn ($r) => $r === MemberSyncWait::SSO_DOWN)->keys()))
            ->get(['id']);

        $queued = 0;
        foreach ($employees as $employee) {
            // A member who must sign in again is not tried: the next sign in runs the sync.
            if (($waiting[$employee->id] ?? null) === MemberSyncWait::LOGIN_REQUIRED) {
                continue;
            }
            SyncMemberDataJob::dispatch($employee->id);
            $queued++;
        }

        $this->info("{$queued} member sync(s) queued, {$waiting->filter(fn ($r) => $r === MemberSyncWait::LOGIN_REQUIRED)->count()} waiting for a new sign in.");

        return self::SUCCESS;
    }
}
