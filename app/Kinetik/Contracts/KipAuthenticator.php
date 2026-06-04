<?php

namespace App\Kinetik\Contracts;

use Illuminate\Http\Client\PendingRequest;

interface KipAuthenticator
{
    /**
     * Apply authentication credentials to a pending HTTP request.
     *
     * Implementations may add headers, query parameters, or middleware as needed.
     * The returned request must be used for subsequent calls.
     */
    public function apply(PendingRequest $request): PendingRequest;
}
