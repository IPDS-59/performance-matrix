<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Auth\StaticBearerAuthenticator;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Sources\ApiKipActivitySource;
use App\Models\KipCredential;
use App\Models\User;
use App\Services\Kinetik\MemberKipToken;
use RuntimeException;

/** Syncs one member's own kegiatan and RK from kipApp, with the member's own token when there is one. */
class SyncMemberActivitiesAction
{
    public function __construct(
        private readonly KipActivitySource $source,
        private readonly SyncKipActivitiesAction $sync,
        private readonly MemberKipToken $tokens,
    ) {}

    /**
     * @param  bool  $allowAdminFallback  false for the scheduled sync: only the member's own token counts, so a member who must sign in again is noticed
     * @return int kegiatan rows upserted (0 when the member has no NIP Lama)
     *
     * @throws RuntimeException with a message for the user when no kipApp token is available
     */
    public function execute(User $user, bool $allowAdminFallback = true): int
    {
        $employee = $user->employee;
        if ($employee === null || empty($employee->nip_lama)) {
            return 0;
        }

        // The member's own token first (T3, T5 in the planning spec): a stored one is renewed with the
        // stored SSO password when it has expired. The admin token is the fallback.
        $source = $this->source;
        try {
            $token = $this->tokens->for($user);
        } catch (RuntimeException $e) {
            $token = null;
            if (! $allowAdminFallback || (KipCredential::current() === null && empty(config('kinetik.kip.token')))) {
                throw $e;
            }
        }

        if ($token !== null && config('kinetik.kip.source') !== 'mock') {
            $source = new ApiKipActivitySource(new StaticBearerAuthenticator($token));
        }

        return $this->sync->execute($source, collect([$employee]));
    }
}
