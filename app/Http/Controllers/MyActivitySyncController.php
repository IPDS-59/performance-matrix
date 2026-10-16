<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\SyncMemberActivitiesAction;
use App\Kinetik\Exceptions\KipApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

/** A member syncs their own kegiatan and RK from kipApp. */
class MyActivitySyncController extends Controller
{
    public function __invoke(Request $request, SyncMemberActivitiesAction $action): RedirectResponse
    {
        $user = $request->user();
        $employee = $user->employee;

        if ($employee === null || empty($employee->nip_lama)) {
            return back()->with('error', 'NIP Lama Anda belum tercatat, jadi belum bisa disinkronkan. Isi NIP di halaman Profil.');
        }

        // The throttle only protects kipApp. A cache fault (for example an unwritable
        // cache folder) must not stop the member from syncing.
        $key = 'sync-mine:'.$user->id;
        try {
            if (RateLimiter::tooManyAttempts($key, 1)) {
                return back()->with('error', 'Sinkronisasi baru saja dijalankan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.');
            }
            RateLimiter::hit($key, 60);
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $count = $action->execute($user);
        } catch (KipApiException $e) {
            return back()->with('error', 'Sinkronisasi gagal: '.$e->getMessage());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Sinkronisasi gagal. Coba lagi nanti.');
        }

        return back()->with('success', "Sinkronisasi selesai: {$count} kegiatan dari kipApp.");
    }
}
