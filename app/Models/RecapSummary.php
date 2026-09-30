<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The PJ's monthly or quarterly narrative for one Projek of a team.
 */
class RecapSummary extends Model
{
    protected $fillable = [
        'team_id',
        'project_id',
        'period_type',
        'period_year',
        'period_month',
        'period_quarter',
        'body',
        'created_by',
    ];

    public function scopeForPeriod(Builder $query, int $teamId, string $type, int $year, ?int $month = null, ?int $quarter = null): Builder
    {
        return $query->where('team_id', $teamId)
            ->where('period_type', $type)
            ->where('period_year', $year)
            ->when($type === 'month', fn (Builder $q) => $q->where('period_month', $month))
            ->when($type === 'quarter', fn (Builder $q) => $q->where('period_quarter', $quarter));
    }
}
