<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecapOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'performance_plan_id',
        'period_type',
        'period_year',
        'period_quarter',
        'period_month',
        'week_start',
        'obstacle',
        'solution',
        'follow_up_plan',
        'follow_up_evidence_url',
        'follow_up_pic_employee_id',
        'follow_up_deadline',
        'created_by',
        'confirmed_at',
        'confirmed_by',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'period_quarter' => 'integer',
        'period_month' => 'integer',
        'week_start' => 'date:Y-m-d',
        'confirmed_at' => 'datetime',
        'follow_up_deadline' => 'date:Y-m-d',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function performancePlan(): BelongsTo
    {
        return $this->belongsTo(PerformancePlan::class);
    }

    public function followUpPic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'follow_up_pic_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'confirmed_by');
    }
}
