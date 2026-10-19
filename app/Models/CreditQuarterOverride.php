<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditQuarterOverride extends Model
{
    protected $fillable = ['employee_id', 'quarter', 'ak', 'computed_ak', 'reason', 'edited_by', 'edited_by_role'];

    protected $casts = ['ak' => 'float', 'computed_ak' => 'float'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
