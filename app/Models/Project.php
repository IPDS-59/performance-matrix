<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'leader_id',
        'name',
        'description',
        'objective',
        'kpi',
        'status',
        'year',
    ];

    protected $casts = [
        'year' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'leader_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }

    public function performanceReports(): HasManyThrough
    {
        return $this->hasManyThrough(PerformanceReport::class, WorkItem::class);
    }
}
