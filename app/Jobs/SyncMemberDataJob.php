<?php

namespace App\Jobs;

use App\Actions\Kinetik\SyncMemberDataAction;
use App\Models\Employee;
use App\Models\MemberSyncWait;
use App\Notifications\KinetikNotification;
use App\Services\Kinetik\MemberLoginRequiredException;
use App\Services\Kinetik\SsoUnavailableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * One member's own sync, run by the scheduler through the queue and again right after the member signs in.
 * When the member's login has run out and cannot be renewed, the member waits in member_sync_waits and is told
 * once. The next sign in runs this job again and clears the wait.
 */
class SyncMemberDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $employeeId) {}

    public function handle(SyncMemberDataAction $sync): void
    {
        $employee = Employee::with('user')->find($this->employeeId);
        if ($employee?->user === null) {
            return;
        }

        try {
            $sync->execute($employee->user);
        } catch (MemberLoginRequiredException $e) {
            $wait = MemberSyncWait::park($employee->id, MemberSyncWait::LOGIN_REQUIRED, $e->getMessage());
            if ($wait->notified_at === null) {
                $employee->user->notify(new KinetikNotification(
                    'sync_login_required',
                    'Data kipApp Anda belum tersinkron karena sesi kipApp berakhir dan tidak bisa diperbarui sendiri. Masuk lagi ke Kinetik dengan kata sandi SSO, lalu sinkronisasi berjalan otomatis.',
                    route('kip.connect'),
                ));
                $wait->update(['notified_at' => now()]);
            }
        } catch (SsoUnavailableException $e) {
            MemberSyncWait::park($employee->id, MemberSyncWait::SSO_DOWN, $e->getMessage());
        } catch (Throwable $e) {
            report($e);
        }
    }
}
