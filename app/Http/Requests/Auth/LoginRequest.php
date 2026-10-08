<?php

namespace App\Http\Requests\Auth;

use App\Actions\Kinetik\ConnectKipAccountAction;
use App\Models\User;
use App\Services\Kinetik\KipLoginException;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! $this->attempt()) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Staff with a BPS SSO username sign in with their SSO password. Until they
     * connect it, the default password still works once (never sent to BPS, so
     * it cannot lock their SSO account). Admins and accounts without an SSO
     * username keep their local password.
     */
    private function attempt(): bool
    {
        $identifier = Str::lower(trim($this->string('email')));
        $domain = (string) config('kinetik.kip.real_email_domain', 'bps.go.id');
        $email = str_contains($identifier, '@') ? $identifier : $identifier.'@'.$domain;
        $password = $this->string('password')->toString();
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->usesKipSso()) {
            return Auth::attempt(['email' => $email, 'password' => $password], $this->boolean('remember'));
        }

        if ($user->needsKipConnection() && ConnectKipAccountAction::defaultPasswordOpen() && Hash::check($password, $user->password)) {
            Auth::login($user, $this->boolean('remember'));

            return true;
        }

        try {
            app(ConnectKipAccountAction::class)->handle($user, $password);
        } catch (KipLoginException $e) {
            if ($e->rejected) {
                return false;
            }
            Log::warning('kipApp SSO login unavailable', ['reason' => $e->getMessage()]);

            // SSO is down: accept the password only if an earlier SSO login already synced it.
            return $user->memberKipCredential()->exists()
                && Auth::attempt(['email' => $email, 'password' => $password], $this->boolean('remember'));
        }

        Auth::login($user, $this->boolean('remember'));

        return true;
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
