<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One periodic SKP rating from kipApp (monthly before 2026, quarterly after).
 */
class KipPerformanceRating extends Model
{
    protected $fillable = [
        'employee_id',
        'kip_skp_id',
        'period_start',
        'period_end',
        'jabatan',
        'predikat',
        'nilai_prestasi',
        'status',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'nilai_prestasi' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
