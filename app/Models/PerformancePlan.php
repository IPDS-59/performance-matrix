<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformancePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'kip_external_id',
        'skp_status',
        'project_id',
        'team_id',
        'code',
        'description',
        'target',
        'target_unit',
        'period_type',
        'period',
        'pic_employee_id',
    ];

    protected $casts = [
        'target' => 'decimal:2',
        'period' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'pic_employee_id');
    }

    public function workItems(): HasMany
    {
        return $this->hasMany(WorkItem::class);
    }
}
