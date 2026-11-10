<?php

namespace App\Kinetik;

use App\Models\PlanItem;

/**
 * Where a kegiatan stands for the person looking at it, derived from three stored facts: status, kip_external_id and
 * kip_sent_at. The frontend twin is resources/js/composables/usePlanStage.ts (labels, colors, next action); the rows
 * sent to the browser carry `stage` so both sides agree.
 */
enum PlanStage: string
{
    case Planned = 'planned';        // next: fill the realisasi, then it goes to kipApp
    case InKipApp = 'in_kipapp';     // next: Tandai selesai
    case InProgress = 'in_progress'; // next: Tandai selesai
    case Done = 'done';              // next: Kirim ke atasan (per quarter)
    case Sent = 'sent';              // next: nothing (Batalkan kirim to change it)
    case Cancelled = 'cancelled';

    public static function of(PlanItem $plan): self
    {
        return match (true) {
            $plan->status === PlanStatus::Cancelled->value => self::Cancelled,
            $plan->kip_sent_at !== null => self::Sent,
            $plan->status === PlanStatus::Done->value => self::Done,
            $plan->status === PlanStatus::InProgress->value => self::InProgress,
            $plan->kip_external_id !== null || $plan->status === PlanStatus::Pushed->value => self::InKipApp,
            default => self::Planned,
        };
    }
}
