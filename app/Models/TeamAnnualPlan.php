<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamAnnualPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'year',
        'kpi',
        'annual_plan',
        'objective_1',
        'objective_2',
        'objective_3',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
