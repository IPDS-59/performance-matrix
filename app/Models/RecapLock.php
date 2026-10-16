<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecapLock extends Model
{
    protected $fillable = [
        'team_id',
        'period_type',
        'period_year',
        'week_start',
        'period_month',
        'period_quarter',
        'locked_by',
    ];

    protected $casts = [
        'week_start' => 'date:Y-m-d',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'locked_by');
    }

    /**
     * The lock for exactly one recap period, if any.
     */
    public static function forPeriod(int $teamId, string $type, int $year, ?int $month = null, ?int $quarter = null, ?string $weekStart = null): ?self
    {
        return static::query()
            ->where('team_id', $teamId)
            ->where('period_type', $type)
            ->where('period_year', $year)
            ->when($type === 'week', fn (Builder $q) => $q->whereDate('week_start', $weekStart))
            ->when($type === 'month', fn (Builder $q) => $q->where('period_month', $month))
            ->when($type === 'quarter', fn (Builder $q) => $q->where('period_quarter', $quarter))
            ->first();
    }
}
