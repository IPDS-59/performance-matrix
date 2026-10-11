<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KipCredential extends Model
{
    protected $fillable = [
        'token',
        'account_nip',
        'account_name',
        'expires_at',
        'updated_by',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    /**
     * The active credential (latest set). Null when none has been stored.
     */
    public static function current(): ?self
    {
        return static::latest('id')->first();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * True when the token is still valid but expires within $hours.
     */
    public function isExpiringSoon(int $hours = 6): bool
    {
        return $this->expires_at !== null
            && ! $this->isExpired()
            && $this->expires_at->isBefore(now()->addHours($hours));
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
