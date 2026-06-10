<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceIndicator extends Model
{
    use HasFactory;

    protected $fillable = [
        'kip_external_id',
        'team_id',
        'year',
        'code',
        'name',
        'target',
        'target_unit',
        'description',
    ];

    protected $casts = [
        'year' => 'integer',
        'target' => 'decimal:2',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
