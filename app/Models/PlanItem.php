<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One planned task of a member: an RK, a date range and a description. */
class PlanItem extends Model
{
    protected $fillable = [
        'team_id', 'employee_id', 'performance_plan_id', 'project_id', 'description',
        'date_start', 'date_end', 'time_start', 'time_end', 'target', 'target_unit', 'capaian', 'capaian_note', 'evidence_url', 'progress', 'source', 'source_ref', 'status',
        'created_by', 'override_status', 'override_reason', 'override_by',
        'kip_external_id', 'kip_skp_id', 'kip_rk_id', 'kip_activity_id', 'pushed_at', 'push_attempts', 'push_error', 'kip_synced_at', 'kip_sent_at',
        'is_capaian_skp', 'parent_id',
    ];

    protected $attributes = ['is_capaian_skp' => true];

    protected $casts = ['target' => 'decimal:2', 'capaian' => 'decimal:2', 'pushed_at' => 'datetime', 'kip_synced_at' => 'datetime', 'kip_sent_at' => 'datetime', 'is_capaian_skp' => 'boolean'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function performancePlan(): BelongsTo
    {
        return $this->belongsTo(PerformancePlan::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The fields of this plan as kipApp's kegiatan form sends them. With KIP_NULL_TIMES on, a one-day
     * kegiatan has no end date and a kegiatan without a clock time has no times (null, like kipApp).
     *
     * @return array<string, mixed>
     */
    public function kipFields(): array
    {
        $start = Carbon::parse($this->date_start)->toDateString();
        $end = Carbon::parse($this->date_end)->toDateString();

        return [
            'tanggal' => $start,
            'tanggalselesai' => config('kinetik.kip.null_times') && $end === $start ? null : $end,
            'progres' => (int) $this->progress,
            'jammulai' => $this->time_start ? substr((string) $this->time_start, 0, 5) : null,
            'jamselesai' => $this->time_end ? substr((string) $this->time_end, 0, 5) : null,
            'capaian' => (string) $this->capaian_note,
            'datadukung' => (string) $this->evidence_url,
            'iscapaianskp' => $this->is_capaian_skp ? 1 : 0,
        ];
    }

    public function scopeDuringWeek(Builder $query, string $weekStart, string $weekEnd): Builder
    {
        return $query->where('status', '!=', 'cancelled')
            ->whereDate('date_start', '<=', $weekEnd)
            ->whereDate('date_end', '>=', $weekStart);
    }
}
