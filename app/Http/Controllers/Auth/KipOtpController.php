<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Kinetik\ConnectKipAccountAction;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Kinetik\KipLoginException;
use App\Services\Kinetik\KipOtpChallenge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Second step of the SSO login for accounts that use a one-time code (OTP). */
class KipOtpController extends Controller
{
    public function show(KipOtpChallenge $challenge): Response|RedirectResponse
    {
        $state = $challenge->current();

        if ($state === null) {
            return redirect()->route(Auth::check() ? 'kip.connect' : 'login');
        }

        return Inertia::render('Auth/KipOtp', ['intent' => $state['intent']]);
    }

    public function store(Request $request, KipOtpChallenge $challenge, ConnectKipAccountAction $connect): RedirectResponse
    {
        $request->validate(['otp' => ['required', 'digits_between:6,8']]);

        $state = $challenge->current();
        $user = $state ? User::find($state['user_id']) : null;

        if ($state === null || $user === null || (Auth::check() && Auth::id() !== $user->id)) {
            $challenge->clear();

            return redirect()->route(Auth::check() ? 'kip.connect' : 'login')
                ->withErrors(['email' => 'Sesi kode OTP berakhir. Masukkan kata sandi Anda lagi.']);
        }

        try {
            $connect->handleOtp($user, $state['password'], $state['pending'], (string) $request->string('otp'));
        } catch (KipLoginException $e) {
            if ($e->otpInvalid && $challenge->retry($state, $e->pending)) {
                throw ValidationException::withMessages(['otp' => 'Kode OTP salah atau sudah berganti. Coba kode terbaru.']);
            }
            $challenge->clear();
            if (! $e->otpInvalid) {
                Log::warning('kipApp SSO OTP unavailable', ['reason' => $e->getMessage()]);
            }

            return redirect()->route(Auth::check() ? 'kip.connect' : 'login')
                ->withErrors([Auth::check() ? 'password' : 'email' => $e->otpInvalid
                    ? 'Terlalu banyak kode OTP yang salah. Masukkan kata sandi Anda lagi.'
                    : 'SSO BPS tidak dapat dihubungi saat ini. Coba lagi nanti.']);
        }

        $challenge->clear();

        if (! Auth::check()) {
            Auth::login($user, $state['remember']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
