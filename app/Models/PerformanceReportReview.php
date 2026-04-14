<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReportReview extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'performance_report_id',
        'actor_id',
        'action',
        'note',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(PerformanceReport::class, 'performance_report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
