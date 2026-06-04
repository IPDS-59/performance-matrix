<?php

namespace App\Kinetik\Auth;

use App\Kinetik\Contracts\KipAuthenticator;
use App\Models\KipCredential;
use Illuminate\Http\Client\PendingRequest;

/**
 * Adds an `x-auth: Bearer <token>` header.
 *
 * Token resolution order:
 *   1. the admin-managed credential in `kip_credentials` (option B — one admin
 *      token drives the centralized sync), then
 *   2. the `KIP_TOKEN` config/env fallback.
 *
 * To swap in an OAuth2 client-credentials flow, bind a different implementation
 * of KipAuthenticator in the service container — no other code changes required.
 */
class ConfigBearerAuthenticator implements KipAuthenticator
{
    public function apply(PendingRequest $request): PendingRequest
    {
        $token = KipCredential::current()?->token ?: config('kinetik.kip.token');

        if (empty($token)) {
            return $request;
        }

        return $request->withHeaders([
            'x-auth' => 'Bearer '.$token,
        ]);
    }
}
