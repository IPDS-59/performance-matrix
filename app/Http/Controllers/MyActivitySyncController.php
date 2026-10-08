<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Auth\StaticBearerAuthenticator;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipActivitySource;
use App\Models\KipCredential;
use App\Services\Kinetik\MemberKipToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Throwable;

/** A member syncs their own kegiatan and RK from kipApp. */
class MyActivitySyncController extends Controller
{
    public function __invoke(Request $request, KipActivitySource $source, SyncKipActivitiesAction $action, MemberKipToken $tokens): RedirectResponse
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

        // The member's own token first (T3, T5 in the planning spec): a stored one is renewed with the
        // stored SSO password when it has expired. The admin token is the fallback.
        try {
            $token = $tokens->for($user);
        } catch (RuntimeException $e) {
            $token = null;
            if (KipCredential::current() === null && empty(config('kinetik.kip.token'))) {
                return back()->with('error', $e->getMessage());
            }
        }

        if ($token !== null && config('kinetik.kip.source') !== 'mock') {
            $source = new ApiKipActivitySource(new StaticBearerAuthenticator($token));
        }

        try {
            $count = $action->execute($source, collect([$employee]));
        } catch (KipApiException $e) {
            return back()->with('error', 'Sinkronisasi gagal: '.$e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', 'Sinkronisasi gagal. Coba lagi nanti.');
        }

        return back()->with('success', "Sinkronisasi selesai: {$count} kegiatan dari kipApp.");
    }
}
