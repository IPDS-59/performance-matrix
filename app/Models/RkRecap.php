<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The PJ's paraphrase plus one target, satuan and capaian for a whole RK in one
 * week or month of a team. The members' own numbers stay on their plan items.
 */
class RkRecap extends Model
{
    protected $fillable = [
        'team_id', 'performance_plan_id', 'period_type', 'period_start',
        'paraphrase', 'target', 'target_unit', 'capaian',
        'locked_at', 'locked_by', 'created_by',
    ];

    protected $casts = [
        'period_start' => 'date:Y-m-d',
        'target' => 'decimal:2',
        'capaian' => 'decimal:2',
        'locked_at' => 'datetime',
    ];

    public function performancePlan(): BelongsTo
    {
        return $this->belongsTo(PerformancePlan::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'locked_by');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function scopeForPeriod(Builder $query, int $teamId, string $type, string $start): Builder
    {
        return $query->where('team_id', $teamId)->where('period_type', $type)->whereDate('period_start', $start);
    }
}
