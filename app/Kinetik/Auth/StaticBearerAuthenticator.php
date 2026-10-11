<?php

namespace App\Kinetik\Auth;

use App\Kinetik\Contracts\KipAuthenticator;
use Illuminate\Http\Client\PendingRequest;

/** Sends one given token, e.g. a member's own kipApp token. */
class StaticBearerAuthenticator implements KipAuthenticator
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    public function apply(PendingRequest $request): PendingRequest
    {
        return $request->withHeaders(['x-auth' => 'Bearer '.$this->token]);
    }
}
