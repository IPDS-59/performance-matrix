<?php

namespace App\Models;

use Carbon\Carbon;
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

    /**
     * True when any lock (week, month or quarter) covers this date for the team.
     * Used to freeze member claims once the PJ locks a period.
     */
    public static function coversDate(int $teamId, Carbon $date): bool
    {
        return static::query()
            ->where('team_id', $teamId)
            ->where('period_year', $date->year)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w->where('period_type', 'week')
                    ->whereDate('week_start', $date->copy()->startOfWeek(Carbon::MONDAY)->toDateString()))
                ->orWhere(fn (Builder $w) => $w->where('period_type', 'month')->where('period_month', $date->month))
                ->orWhere(fn (Builder $w) => $w->where('period_type', 'quarter')->where('period_quarter', $date->quarter)))
            ->exists();
    }
}
