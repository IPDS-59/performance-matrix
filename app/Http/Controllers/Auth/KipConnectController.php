<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Kinetik\ConnectKipAccountAction;
use App\Http\Controllers\Controller;
use App\Services\Kinetik\KipLoginException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KipConnectController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->needsKipConnection()) {
            return redirect()->route('dashboard');
        }

        $until = config('kinetik.kip.default_password_until');

        return Inertia::render('Auth/ConnectKip', [
            'username' => $request->user()->kipUsername(),
            'deadline' => $until ?: null,
        ]);
    }

    public function store(Request $request, ConnectKipAccountAction $connect): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();
        $key = 'kip-connect:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'password' => 'Terlalu banyak percobaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.',
            ]);
        }

        try {
            $connect->handle($user, (string) $request->string('password'));
        } catch (KipLoginException $e) {
            if ($e->rejected) {
                RateLimiter::hit($key);
                throw ValidationException::withMessages(['password' => 'Kata sandi SSO BPS salah.']);
            }
            Log::warning('kipApp SSO connect unavailable', ['reason' => $e->getMessage()]);
            throw ValidationException::withMessages(['password' => 'SSO BPS tidak dapat dihubungi saat ini. Coba lagi nanti.']);
        }

        RateLimiter::clear($key);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
