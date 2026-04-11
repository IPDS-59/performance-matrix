<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'number',
        'description',
    ];

    protected $casts = [
        'number' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function performanceReports(): HasMany
    {
        return $this->hasMany(PerformanceReport::class);
    }
}
