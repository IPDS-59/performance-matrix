<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamRecapEvidence extends Model
{
    use HasFactory;

    protected $table = 'team_recap_evidences';

    protected $fillable = [
        'team_id',
        'project_id',
        'period_type',
        'period_year',
        'week_start',
        'period_quarter',
        'period_month',
        'type',
        'title',
        'url',
        'uploaded_by',
    ];

    protected $casts = [
        'week_start' => 'date',
        'period_year' => 'integer',
        'period_quarter' => 'integer',
        'period_month' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'uploaded_by');
    }
}
