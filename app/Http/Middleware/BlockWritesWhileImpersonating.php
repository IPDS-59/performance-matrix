<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin views the app as someone else, nothing may be written: no saves, no pushes to
 * kipApp on that person's behalf. Only leaving the view, and signing out, still work.
 */
class BlockWritesWhileImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has(ImpersonationController::SESSION_KEY)
            && ! $request->isMethodSafe()
            && ! $request->routeIs('admin.impersonate.stop', 'logout')) {
            return back()->with('error', 'Mode lihat saja: perubahan dinonaktifkan selama Anda melihat sebagai pengguna lain.');
        }

        return $next($request);
    }
}
