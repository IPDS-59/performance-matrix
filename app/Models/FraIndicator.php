<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One IKU or proksi row of the Kertas Kerja LK_Prov sheet. */
class FraIndicator extends Model
{
    protected $fillable = [
        'year', 'sort_order', 'tujuan', 'sasaran_code', 'sasaran_name', 'code', 'name', 'kind', 'period_type',
        'unit_type', 'percent_label', 'unit', 'x_label', 'y_label', 'target_x', 'target_y', 'owner_team_id',
    ];

    protected $casts = ['target_x' => 'float', 'target_y' => 'float', 'percent_label' => 'boolean'];

    public function ownerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'owner_team_id');
    }

    public function quarterValues(): HasMany
    {
        return $this->hasMany(FraQuarterValue::class);
    }

    public function isPercent(): bool
    {
        return $this->unit_type === 'percent';
    }
}
