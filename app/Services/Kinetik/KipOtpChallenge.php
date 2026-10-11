<?php

namespace App\Services\Kinetik;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;

/**
 * Holds a half-finished SSO login while the member types the one-time code.
 * The state (SSO cookies and the member's password) lives encrypted in the
 * session for a few minutes, never in the database.
 */
class KipOtpChallenge
{
    private const KEY = 'kip_otp_challenge';

    private const MINUTES = 5;

    private const MAX_TRIES = 5;

    /**
     * @param  array{action: string, cookies: array<int, array<string, mixed>>}  $pending
     */
    public function start(array $pending, User $user, #[\SensitiveParameter] string $password, bool $remember, string $intent): RedirectResponse
    {
        $this->store([
            'pending' => $pending,
            'user_id' => $user->id,
            'password' => $password,
            'remember' => $remember,
            'intent' => $intent,
            'expires' => now()->addMinutes(self::MINUTES)->getTimestamp(),
            'tries' => 0,
        ]);

        return redirect()->route('kip.otp');
    }

    /**
     * @return array{pending: array<string, mixed>, user_id: int, password: string, remember: bool, intent: string, expires: int, tries: int}|null
     */
    public function current(): ?array
    {
        $raw = session(self::KEY);
        if (! is_string($raw)) {
            return null;
        }

        try {
            $state = json_decode(Crypt::decryptString($raw), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        return ($state['expires'] ?? 0) >= now()->getTimestamp() ? $state : null;
    }

    /** Records a wrong code with the fresh SSO form. False when the member has no tries left. */
    public function retry(array $state, array $pending): bool
    {
        $state['pending'] = $pending;
        $state['tries']++;
        $this->store($state);

        return $state['tries'] < self::MAX_TRIES;
    }

    public function clear(): void
    {
        session()->forget(self::KEY);
    }

    private function store(array $state): void
    {
        session([self::KEY => Crypt::encryptString(json_encode($state))]);
    }
}
