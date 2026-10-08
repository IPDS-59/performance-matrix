<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Staff with a BPS SSO username sign in with SSO. Admins and local-only accounts never do. */
    public function usesKipSso(): bool
    {
        $domain = (string) config('kinetik.kip.real_email_domain', 'bps.go.id');

        return ! ($this->role === 'admin' || $this->hasRole('admin'))
            && str_ends_with((string) $this->email, '@'.$domain)
            && $this->employee()->exists();
    }

    public function kipUsername(): string
    {
        return Str::before((string) $this->email, '@');
    }

    /** True until the member has stored their SSO password. Not enforced while no key is set. */
    public function needsKipConnection(): bool
    {
        return MemberKipCredential::keyIsConfigured()
            && $this->usesKipSso()
            && ! $this->memberKipCredential()->whereNull('login_failed_at')->exists();
    }

    /** @return Collection<int, User> */
    public static function unconnectedKipStaff(): Collection
    {
        return static::whereDoesntHave('memberKipCredential')->whereHas('employee')->orderBy('name')->get()
            ->filter(fn (User $u) => $u->usesKipSso())->values();
    }

    public function memberKipCredential(): HasOne
    {
        return $this->hasOne(MemberKipCredential::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }
}
