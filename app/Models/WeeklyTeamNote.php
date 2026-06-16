<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyTeamNote extends Model
{
    protected $fillable = [
        'team_id',
        'week_start',
        'uraian',
        'obstacle',
        'solution',
        'follow_up_plan',
        'created_by',
    ];

    protected $casts = [
        'week_start' => 'date',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'created_by');
    }
}
