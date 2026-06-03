<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityClaim extends Model
{
    use HasFactory;

    protected $fillable = [
        'kip_activity_id',
        'employee_id',
        'performance_plan_id',
        'work_item_id',
        'target',
        'realization',
        'achievement',
        'target_unit',
        'obstacle',
        'solution',
        'follow_up_plan',
        'activity_date_start',
        'activity_date_end',
        'start_time',
        'end_time',
        'evidence_url',
        'status',
        'week_start',
        'period_year',
        'period_quarter',
        'period_month',
        'reserved_1',
        'reserved_2',
        'reserved_3',
        'claimed_at',
    ];

    protected $casts = [
        'activity_date_start' => 'date',
        'activity_date_end' => 'date',
        'week_start' => 'date',
        'claimed_at' => 'datetime',
        'target' => 'decimal:2',
        'realization' => 'decimal:2',
        'achievement' => 'decimal:2',
        'period_year' => 'integer',
        'period_quarter' => 'integer',
        'period_month' => 'integer',
    ];

    public function kipActivity(): BelongsTo
    {
        return $this->belongsTo(KipActivity::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function performancePlan(): BelongsTo
    {
        return $this->belongsTo(PerformancePlan::class);
    }

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }
}
