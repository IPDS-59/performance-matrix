<?php

namespace App\Services\Kinetik;

use App\Models\MemberKipCredential;
use App\Models\User;
use App\Notifications\KinetikNotification;
use RuntimeException;

/**
 * The kipApp token of a member, for writes and own reads (T3, T4, T5).
 * A valid stored token is used as it is. An expired one is renewed with the
 * stored SSO password. After one rejected login the password is marked failed
 * and never sent again until the member enters it again (T4).
 */
class MemberKipToken
{
    public function __construct(private readonly KipLoginClient $login) {}

    /**
     * @throws RuntimeException when no usable token exists, with a message for the member
     */
    public function for(User $user): string
    {
        $credential = $user->memberKipCredential;

        if ($credential === null || $credential->login_failed_at !== null) {
            throw new RuntimeException('Akun SSO belum terhubung atau perlu diperbarui. Masuk ke Kinetik dengan kata sandi SSO Anda.');
        }

        if ($credential->hasValidToken()) {
            return $credential->plainToken();
        }

        try {
            $result = $this->login->login($user->kipUsername(), $credential->plainPassword());
        } catch (KipLoginException $e) {
            if (! $e->rejected) {
                throw new RuntimeException('SSO BPS tidak dapat dihubungi saat ini. Akan dicoba lagi.');
            }

            $credential->markFailed('Kata sandi SSO ditolak');
            $user->notify(new KinetikNotification(
                'kip_password_failed',
                'Kata sandi SSO Anda ditolak BPS, jadi Kinetik berhenti mengakses kipApp atas nama Anda. Masuk lagi dengan kata sandi SSO yang baru.',
                route('kip.connect'),
            ));

            throw new RuntimeException('Kata sandi SSO ditolak. Masuk lagi dengan kata sandi SSO yang baru.');
        }

        MemberKipCredential::remember($user, $credential->plainPassword(), $result->token, $result->expiresAt);

        return $result->token;
    }
}
