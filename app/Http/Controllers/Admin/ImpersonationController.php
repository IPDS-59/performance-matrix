<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets an admin look at the app as a member, a PJ or the head, to reproduce what they see.
 * The view is read only (see BlockWritesWhileImpersonating) and admins cannot be impersonated.
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole('admin'), 403);

        $leaderIds = Team::query()->pluck('leader_id')->filter()->unique();

        $users = User::query()->with('employee:id,user_id,name,display_name')
            ->where(fn ($q) => $q->where('role', 'head')->orWhereHas('employee'))
            ->orderBy('name')->get()
            ->reject(fn (User $u) => $u->hasRole('admin') || $u->id === $request->user()->id)
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->employee?->display_name ?? $u->employee?->name ?? $u->name,
                'email' => $u->email,
                'level' => $u->role === 'head' || $u->hasRole('head') ? 'pimpinan' : ($u->employee && $leaderIds->contains($u->employee->id) ? 'pj' : 'anggota'),
            ])->values();

        return Inertia::render('Admin/Impersonate', ['users' => $users]);
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin->hasRole('admin'), 403);
        abort_if($request->session()->has(self::SESSION_KEY), 422, 'Keluar dulu dari tampilan sebagai pengguna lain.');
        abort_if($user->hasRole('admin'), 403, 'Akun admin tidak dapat ditampilkan sebagai.');
        abort_if($user->id === $admin->id, 422);

        Log::info('impersonation started', ['admin_id' => $admin->id, 'user_id' => $user->id]);

        $request->session()->put(self::SESSION_KEY, $admin->id);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $admin->id);

        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        $adminId = $request->session()->get(self::SESSION_KEY);
        abort_if($adminId === null, 404);

        $admin = User::findOrFail($adminId);
        Log::info('impersonation stopped', ['admin_id' => $admin->id, 'user_id' => $request->user()?->id]);

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->route('admin.impersonate');
    }
}
