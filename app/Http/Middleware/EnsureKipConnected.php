<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sends staff who have not stored their SSO password to the connect page. */
class EnsureKipConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $request->routeIs('kip.connect*', 'logout') && $user->needsKipConnection()) {
            return redirect()->route('kip.connect');
        }

        return $next($request);
    }
}
