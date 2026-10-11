<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraQuarterValue extends Model
{
    protected $fillable = [
        'fra_indicator_id', 'quarter', 'allocation_x', 'allocation_y', 'realization_x', 'realization_y',
        'obstacle', 'solution', 'follow_up', 'pic', 'deadline', 'evidence_url', 'previous_follow_up_url', 'updated_by',
    ];

    protected $casts = ['allocation_x' => 'float', 'allocation_y' => 'float', 'realization_x' => 'float', 'realization_y' => 'float'];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(FraIndicator::class, 'fra_indicator_id');
    }
}
