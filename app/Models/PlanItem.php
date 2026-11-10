<?php

namespace App\Models;

use App\Kinetik\PlanStage;
use App\Kinetik\PlanStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/** One planned task of a member: an RK, a date range and a description. */
class PlanItem extends Model
{
    protected $fillable = [
        'team_id', 'employee_id', 'performance_plan_id', 'project_id', 'description',
        'date_start', 'date_end', 'time_start', 'time_end', 'target', 'target_unit', 'capaian', 'capaian_note', 'kendala', 'evidence_url', 'progress', 'source', 'source_ref', 'status',
        'created_by', 'override_status', 'override_reason', 'override_by',
        'kip_external_id', 'kip_skp_id', 'kip_rk_id', 'kip_activity_id', 'pushed_at', 'push_attempts', 'push_error', 'kip_synced_at', 'kip_sent_at',
        'is_capaian_skp', 'parent_id', 'merged_into_id',
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

    /**
     * What is still missing before this kegiatan may go to kipApp: only a finished one is sent, with progres 100, the capaian
     * text (kipApp asks 10 characters), the bukti dukung, and the capaian number when a target was set.
     *
     * @return list<string>
     */
    public function missingForKip(): array
    {
        if (! config('kinetik.kip.push_only_finished')) {
            return [];
        }

        $missing = [];
        if ((int) $this->progress < 100) {
            $missing[] = 'progres 100';
        }
        if ($this->target !== null && $this->capaian === null) {
            $missing[] = 'capaian (angka)';
        }
        if (mb_strlen(trim((string) $this->capaian_note)) < 10) {
            $missing[] = 'uraian capaian (minimal 10 karakter)';
        }
        if (blank($this->evidence_url)) {
            $missing[] = 'bukti dukung';
        }

        return $missing;
    }

    /** @return HasMany<PlanItemWeek, $this> */
    public function weekSnapshots(): HasMany
    {
        return $this->hasMany(PlanItemWeek::class);
    }

    /** The lifecycle stage a person sees (status + kip_external_id + kip_sent_at). */
    public function stage(): PlanStage
    {
        return PlanStage::of($this);
    }

    /**
     * A status change that the lifecycle does not know (out of "cancelled", or back to "planned") is written to the log, not
     * blocked: the log shows the caller to fix, and no one's save fails over it.
     */
    protected static function booted(): void
    {
        static::updating(function (self $plan) {
            if (! $plan->isDirty('status')) {
                return;
            }
            $from = PlanStatus::tryFrom((string) $plan->getOriginal('status'));
            $to = PlanStatus::tryFrom((string) $plan->status);
            if ($from === null || $to === null || ! $from->canBecome($to)) {
                Log::warning('plan item: unexpected status change', ['id' => $plan->id, 'from' => $plan->getOriginal('status'), 'to' => $plan->status]);
            }
        });
    }

    /** Whether the dates reach into more than one Monday-to-Sunday week. */
    public function spansWeeks(): bool
    {
        return Carbon::parse($this->date_start)->startOfWeek(Carbon::MONDAY)->ne(Carbon::parse($this->date_end)->startOfWeek(Carbon::MONDAY));
    }

    public function scopeDuringWeek(Builder $query, string $weekStart, string $weekEnd): Builder
    {
        return $query->where('status', '!=', 'cancelled')
            ->whereDate('date_start', '<=', $weekEnd)
            ->whereDate('date_end', '>=', $weekStart);
    }
}
