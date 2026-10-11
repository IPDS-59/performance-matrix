<?php

namespace App\Actions\Kinetik;

use App\Models\MemberKipCredential;
use App\Models\User;
use App\Services\Kinetik\KipLoginClient;
use App\Services\Kinetik\KipLoginException;
use App\Services\Kinetik\KipLoginResult;
use Illuminate\Support\Carbon;

/**
 * Checks a member's SSO password with BPS, then stores it (encrypted) with the
 * kipApp token and makes it the local password too.
 */
class ConnectKipAccountAction
{
    public function __construct(private readonly KipLoginClient $client) {}

    /**
     * @throws KipLoginException
     */
    public function handle(User $user, #[\SensitiveParameter] string $password): void
    {
        $this->store($user, $password, $this->client->login($user->kipUsername(), $password));
    }

    /**
     * Second step for accounts with OTP.
     *
     * @param  array{action: string, cookies: array<int, array<string, mixed>>}  $pending
     *
     * @throws KipLoginException
     */
    public function handleOtp(User $user, #[\SensitiveParameter] string $password, array $pending, string $code): void
    {
        $this->store($user, $password, $this->client->loginWithOtp($pending, $code));
    }

    private function store(User $user, #[\SensitiveParameter] string $password, KipLoginResult $result): void
    {
        $user->forceFill(['password' => $password])->save();

        if (MemberKipCredential::keyIsConfigured()) {
            MemberKipCredential::remember($user, $password, $result->token, $result->expiresAt);
        }
    }

    /** True while staff may still first-login with the default password. */
    public static function defaultPasswordOpen(): bool
    {
        $until = config('kinetik.kip.default_password_until');

        return ! $until || now(config('app.schedule_timezone', 'Asia/Makassar'))->lte(
            Carbon::parse($until, config('app.schedule_timezone', 'Asia/Makassar'))->endOfDay()
        );
    }
}
