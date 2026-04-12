<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_item_id',
        'reported_by',
        'period_month',
        'period_year',
        'realization',
        'achievement_percentage',
        'issues',
        'solutions',
        'action_plan',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'period_year' => 'integer',
        'realization' => 'decimal:2',
        'achievement_percentage' => 'decimal:2',
    ];

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reported_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }
}
