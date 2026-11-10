<?php

namespace App\Kinetik;

/**
 * The stored status of a plan item (plan_items.status). Together with kip_external_id and kip_sent_at it gives the
 * lifecycle stage a person sees: see PlanStage and docs/architecture/state-machines.md.
 *
 * The column stays a plain string (no Eloquent cast), so `$plan->status === 'planned'` keeps working everywhere.
 */
enum PlanStatus: string
{
    case Planned = 'planned';        // only in Kinetik
    case Pushed = 'pushed';          // in kipApp, progres 0
    case InProgress = 'in_progress'; // in kipApp, progres 1 to 99
    case Done = 'done';              // in kipApp, progres 100
    case Cancelled = 'cancelled';    // cancelled here, deleted in kipApp, or folded into another kegiatan

    /** The one rule for a kegiatan that is in kipApp: its status follows the kipApp progres. */
    public static function fromKipProgress(int|float $progress): self
    {
        return match (true) {
            $progress >= 100 => self::Done,
            $progress > 0 => self::InProgress,
            default => self::Pushed,
        };
    }

    /** Cancelled is final, and nothing goes back to "only in Kinetik" once it left. */
    public function canBecome(self $next): bool
    {
        return $this === $next || ($this !== self::Cancelled && $next !== self::Planned);
    }

    public function inKipApp(): bool
    {
        return in_array($this, [self::Pushed, self::InProgress, self::Done], true);
    }
}
