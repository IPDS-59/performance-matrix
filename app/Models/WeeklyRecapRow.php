<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** The PJ's text for one kegiatan of a week, or for several kegiatan merged together. */
class WeeklyRecapRow extends Model
{
    protected $fillable = ['team_id', 'project_id', 'week_start', 'pj_uraian', 'obstacle', 'solution', 'follow_up_plan', 'saved_at', 'created_by'];

    protected $casts = ['saved_at' => 'datetime'];

    public function claims(): BelongsToMany
    {
        return $this->belongsToMany(ActivityClaim::class, 'weekly_recap_row_claims');
    }
}
