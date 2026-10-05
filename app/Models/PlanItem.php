<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One planned task of a member: an RK, a date range and a description. */
class PlanItem extends Model
{
    protected $fillable = [
        'team_id', 'employee_id', 'performance_plan_id', 'project_id', 'description',
        'date_start', 'date_end', 'target', 'target_unit', 'source', 'source_ref', 'status',
        'created_by',
    ];

    protected $casts = ['target' => 'decimal:2'];

    public function performancePlan(): BelongsTo
    {
        return $this->belongsTo(PerformancePlan::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeDuringWeek(Builder $query, string $weekStart, string $weekEnd): Builder
    {
        return $query->where('status', '!=', 'cancelled')
            ->whereDate('date_start', '<=', $weekEnd)
            ->whereDate('date_end', '>=', $weekStart);
    }
}
