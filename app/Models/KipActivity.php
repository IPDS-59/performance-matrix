<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KipActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'external_id',
        'nip_lama',
        'description',
        'activity_date_start',
        'activity_date_end',
        'time_start',
        'time_end',
        'evidence_url',
        'rk_external_id',
        'rk_name',
        'progress',
        'achievement_note',
        'period_id',
        'source_year',
        'sent_at',
        'raw_payload',
        'fetched_at',
        'is_claimed',
        'reserved_1',
        'reserved_2',
        'reserved_3',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'is_claimed' => 'boolean',
        'fetched_at' => 'datetime',
        'activity_date_start' => 'date',
        'activity_date_end' => 'date',
        'sent_at' => 'date',
        'progress' => 'integer',
        'source_year' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Activities that run during a period (a week, month or quarter): they start
     * in it, or started earlier and end in or after its first day. A monthly
     * task counts in every week it covers.
     */
    public function scopeDuringWeek(Builder $query, string $weekStart, string $weekEnd): Builder
    {
        return $query->whereDate('activity_date_start', '<=', $weekEnd)
            ->where(fn (Builder $q) => $q->whereDate('activity_date_start', '>=', $weekStart)
                ->orWhereDate('activity_date_end', '>=', $weekStart));
    }

    /**
     * One claim per week the activity covers (a monthly task is claimed weekly).
     */
    public function claims(): HasMany
    {
        return $this->hasMany(ActivityClaim::class);
    }
}
