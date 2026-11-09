<?php

namespace App\Services\Kinetik;

use App\Models\PlanItem;
use Carbon\Carbon;

/**
 * What a kegiatan shows in one week. A kegiatan inside one week shows its own capaian, progres and permasalahan. One that runs for
 * several weeks shows what was recorded for that week, so the weekly meeting sees the progress of the week. A week with nothing
 * recorded shows nothing yet, except the last week of the kegiatan, which shows the final values the kegiatan itself holds.
 */
class WeekSnapshot
{
    /** @return array{capaian: ?float, progress: int, kendala: ?string} */
    public static function of(PlanItem $p, Carbon $monday): array
    {
        $own = ['capaian' => $p->capaian !== null ? (float) $p->capaian : null, 'progress' => (int) $p->progress, 'kendala' => $p->kendala];
        if (! $p->spansWeeks()) {
            return $own;
        }

        $monday = $monday->copy()->startOfWeek(Carbon::MONDAY);
        $snap = $p->weekSnapshots->first(fn ($w) => $w->week_start->toDateString() === $monday->toDateString());
        if ($snap !== null) {
            return ['capaian' => $snap->capaian !== null ? (float) $snap->capaian : null, 'progress' => (int) $snap->progress, 'kendala' => $snap->kendala];
        }

        return Carbon::parse($p->date_end)->startOfWeek(Carbon::MONDAY)->eq($monday) ? $own : ['capaian' => null, 'progress' => 0, 'kendala' => null];
    }

    /** Records the values of one week of a kegiatan that runs for several weeks; a kegiatan inside one week has no snapshots. */
    public static function record(PlanItem $p, Carbon $monday, array $values): void
    {
        if (! $p->spansWeeks()) {
            return;
        }
        $p->weekSnapshots()->updateOrCreate(
            ['week_start' => $monday->copy()->startOfWeek(Carbon::MONDAY)->toDateString()],
            ['capaian' => $values['capaian'] ?? null, 'progress' => $values['progress'] ?? null, 'kendala' => $values['kendala'] ?? null],
        );
    }
}
