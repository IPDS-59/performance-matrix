<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KipSyncRun extends Model
{
    /** The run lifecycle: running, then completed or failed. The browser drives a run chunk by chunk (KipIntegrationController). */
    public const RUNNING = 'running';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    protected $fillable = [
        'type',
        'status',
        'total',
        'processed',
        'pending',
        'summary',
        'message',
        'user_id',
        'finished_at',
    ];

    protected $casts = [
        'total' => 'integer',
        'processed' => 'integer',
        'pending' => 'array',
        'summary' => 'array',
        'finished_at' => 'datetime',
    ];

    /**
     * The latest still-running sync of a given type (for resume on reload).
     */
    public static function active(string $type): ?self
    {
        return static::where('type', $type)
            ->where('status', self::RUNNING)
            ->latest('id')
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
