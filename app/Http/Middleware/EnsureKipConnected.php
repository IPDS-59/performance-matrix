<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sends staff who have not stored their SSO password to the connect page. */
class EnsureKipConnected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // An admin looking at the app as someone else must see their screens, connected or not.
        if ($user && ! $request->session()->has(ImpersonationController::SESSION_KEY) && ! $request->routeIs('kip.connect*', 'kip.otp*', 'logout') && $user->needsKipConnection()) {
            return redirect()->route('kip.connect');
        }

        return $next($request);
    }
}
