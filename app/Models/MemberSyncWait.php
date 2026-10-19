<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member whose own sync is waiting: for a new login, or for BPS SSO to come back. */
class MemberSyncWait extends Model
{
    public const LOGIN_REQUIRED = 'login_required';

    public const SSO_DOWN = 'sso_down';

    protected $fillable = ['employee_id', 'reason', 'message', 'queued_at', 'last_attempt_at', 'attempts', 'notified_at'];

    protected $casts = ['queued_at' => 'datetime', 'last_attempt_at' => 'datetime', 'notified_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Put a member in the wait list, or update the reason when they already are in it. */
    public static function park(int $employeeId, string $reason, string $message): self
    {
        $wait = static::firstOrNew(['employee_id' => $employeeId]);
        $wait->fill([
            'reason' => $reason,
            'message' => mb_substr($message, 0, 300),
            'queued_at' => $wait->queued_at ?? now(),
            'last_attempt_at' => now(),
            'attempts' => $wait->attempts + 1,
        ])->save();

        return $wait;
    }
}
