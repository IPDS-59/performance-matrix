<?php

namespace App\Kinetik\Auth;

use App\Kinetik\Contracts\KipAuthenticator;
use Illuminate\Http\Client\PendingRequest;

/**
 * Adds an `x-auth: Bearer <token>` header using the token stored in config.
 *
 * This is the default implementation and covers the known auth mechanism
 * (per-user ~24h JWT captured from browser DevTools / official server API key).
 *
 * To swap in an OAuth2 client-credentials flow, bind a different implementation
 * of KipAuthenticator in the service container — no other code changes required.
 */
class ConfigBearerAuthenticator implements KipAuthenticator
{
    public function apply(PendingRequest $request): PendingRequest
    {
        $token = config('kinetik.kip.token');

        if (empty($token)) {
            return $request;
        }

        return $request->withHeaders([
            'x-auth' => 'Bearer '.$token,
        ]);
    }
}
