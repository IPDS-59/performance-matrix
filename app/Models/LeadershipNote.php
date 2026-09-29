<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadershipNote extends Model
{
    protected $fillable = [
        'team_id',
        'project_id',
        'period_type',
        'period_year',
        'week_start',
        'period_month',
        'period_quarter',
        'body',
        'author_id',
    ];

    // week_start stays a plain Y-m-d string (no date cast), so the exact
    // match in updateOrCreate finds the stored row.

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Notes of one recap period, every team.
     */
    public function scopeForPeriod(Builder $query, string $type, int $year, ?int $month = null, ?int $quarter = null, ?string $weekStart = null): Builder
    {
        return $query
            ->where('period_type', $type)
            ->where('period_year', $year)
            ->when($type === 'week', fn (Builder $q) => $q->whereDate('week_start', $weekStart))
            ->when($type === 'month', fn (Builder $q) => $q->where('period_month', $month))
            ->when($type === 'quarter', fn (Builder $q) => $q->where('period_quarter', $quarter));
    }
}
