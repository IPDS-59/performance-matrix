<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Models\ImpersonationAudit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While an admin acts as someone else, every write goes through as that person and is written to the audit table
 * (who, as whom, which route). Only what would take over the person's identity stays blocked: password, profile,
 * login to kipApp and account deletion.
 */
class AuditImpersonatedWrites
{
    private const IDENTITY_ROUTES = ['password.update', 'profile.update', 'profile.nip.update', 'profile.destroy', 'kip.connect.store', 'kip.otp.store'];

    public function handle(Request $request, Closure $next): Response
    {
        $adminId = $request->session()->get(ImpersonationController::SESSION_KEY);
        if ($adminId === null || $request->isMethodSafe() || $request->routeIs('admin.impersonate.stop', 'logout')) {
            return $next($request);
        }

        if ($request->routeIs(...self::IDENTITY_ROUTES)) {
            return back()->with('error', 'Perubahan akun (kata sandi, profil, login kipApp) tidak bisa dilakukan sambil bertindak sebagai pengguna lain.');
        }

        $response = $next($request);

        ImpersonationAudit::create([
            'admin_id' => $adminId,
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'route' => (string) $request->route()?->getName(),
            'path' => mb_substr($request->path(), 0, 255),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}
