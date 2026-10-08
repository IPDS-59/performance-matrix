<?php

namespace App\Actions\Kinetik;

use App\Models\MemberKipCredential;
use App\Models\User;
use App\Services\Kinetik\KipLoginClient;
use App\Services\Kinetik\KipLoginException;
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
        $result = $this->client->login($user->kipUsername(), $password);

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
