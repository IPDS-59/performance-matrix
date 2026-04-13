<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkItemAssignment extends Model
{
    protected $fillable = [
        'work_item_id',
        'employee_id',
        'target',
        'target_unit',
    ];

    protected $casts = [
        'target' => 'decimal:2',
    ];

    public function workItem(): BelongsTo
    {
        return $this->belongsTo(WorkItem::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
