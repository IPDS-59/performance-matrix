<?php

namespace App\Services\Kinetik;

use App\Models\MemberSkpStatus;
use App\Models\PlanItem;
use Carbon\Carbon;

/**
 * What may still be done with a plan item that is already in kipApp, from the two settings and the
 * status of the quarter SKP (kipApp locks a SKP that left "Sedang dibuat"). An unknown SKP status
 * counts as open, so a missing value never locks a member out.
 */
class PlanItemAccess
{
    public const LOCK_REASON = 'SKP triwulan sudah dikirim untuk dinilai.';

    public const NOT_STARTED = 'Kegiatan belum bisa dikirim sebelum tanggal mulainya.';

    /** kipApp: 1 = sedang dibuat, 6 = dikembalikan ke pegawai. */
    private const EDITABLE = [1, 6];

    private const SENDABLE = [1];

    public function canEdit(PlanItem $plan): bool
    {
        return match (true) {
            $plan->status === 'cancelled' => false,
            ! $this->inKip($plan) => $plan->status === 'planned',
            default => (bool) config('kinetik.kip.edit_after_push') && $this->open($plan, self::EDITABLE),
        };
    }

    public function canDelete(PlanItem $plan): bool
    {
        return match (true) {
            $plan->status === 'cancelled' => false,
            ! $this->inKip($plan) => $plan->status === 'planned',
            default => (bool) config('kinetik.kip.edit_after_push') && $plan->kip_sent_at === null && $this->open($plan, self::EDITABLE),
        };
    }

    public function canSend(PlanItem $plan): bool
    {
        return (bool) config('kinetik.kip.send_to_atasan') && $this->inKip($plan) && $plan->status !== 'planned' && $plan->status !== 'cancelled' && $this->skpAllowsSend($plan);
    }

    /** Only a SKP that is still "Sedang dibuat" (or of unknown status) accepts send and cancel. */
    public function skpAllowsSend(PlanItem $plan): bool
    {
        return $this->open($plan, self::SENDABLE);
    }

    /** Why the SKP blocks this kegiatan, or null when it does not. */
    public function lockReason(PlanItem $plan): ?string
    {
        return $this->inKip($plan) && ! $this->open($plan, self::EDITABLE) ? self::LOCK_REASON : null;
    }

    /** kipApp accepts no future date, so a plan goes there on its start date at the earliest. */
    public function hasStarted(PlanItem $plan): bool
    {
        return Carbon::parse($plan->date_start)->toDateString() <= now(config('app.schedule_timezone', 'Asia/Makassar'))->toDateString();
    }

    public function quarterOf(PlanItem $plan): int
    {
        return Carbon::parse($plan->date_start)->quarter;
    }

    private function inKip(PlanItem $plan): bool
    {
        return $plan->kip_external_id !== null;
    }

    /** @param  list<int>  $allowed */
    private function open(PlanItem $plan, array $allowed): bool
    {
        $date = Carbon::parse($plan->date_start);
        $status = MemberSkpStatus::idFor($plan->employee_id, $date->year, $date->quarter);

        return $status === null || in_array($status, $allowed, true);
    }
}
