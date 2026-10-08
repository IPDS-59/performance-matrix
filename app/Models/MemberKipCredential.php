<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * A member's BPS SSO password and kipApp token, encrypted with their own key.
 * The UI never reads these back (T1 in docs/kinetik/spec-planning.md).
 */
class MemberKipCredential extends Model
{
    protected $table = 'member_kip_credentials';

    protected $fillable = ['user_id', 'password', 'token', 'token_expires_at', 'last_login_at', 'login_failed_at', 'last_error'];

    protected $hidden = ['password', 'token'];

    protected function casts(): array
    {
        return ['token_expires_at' => 'datetime', 'last_login_at' => 'datetime', 'login_failed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function keyIsConfigured(): bool
    {
        return (string) config('kinetik.kip.credential_key') !== '';
    }

    public static function remember(User $user, string $password, string $token, \DateTimeInterface $expiresAt): self
    {
        $crypt = self::crypt();

        return self::updateOrCreate(['user_id' => $user->id], [
            'password' => $crypt->encryptString($password),
            'token' => $crypt->encryptString($token),
            'token_expires_at' => $expiresAt,
            'last_login_at' => now(),
            'login_failed_at' => null,
            'last_error' => null,
        ]);
    }

    public function hasValidToken(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isFuture() && $this->getRawOriginal('token') !== null;
    }

    public function markFailed(string $reason): void
    {
        $this->forceFill(['login_failed_at' => now(), 'last_error' => $reason, 'token' => null, 'token_expires_at' => null])->save();
    }

    public function plainPassword(): string
    {
        return self::crypt()->decryptString($this->attributes['password']);
    }

    public function plainToken(): ?string
    {
        return $this->attributes['token'] === null ? null : self::crypt()->decryptString($this->attributes['token']);
    }

    private static function crypt(): Encrypter
    {
        $key = (string) config('kinetik.kip.credential_key');
        if ($key === '') {
            throw new RuntimeException('KIP_CREDENTIAL_KEY is not set.');
        }

        return new Encrypter(base64_decode(str_replace('base64:', '', $key)), 'AES-256-CBC');
    }
}
