<?php

namespace App\Services\Kinetik;

use App\Models\PlanItem;
use App\Models\SkpStatus;
use Carbon\CarbonImmutable;

/**
 * What a member did under each RK in the quarter of a SKP, from Kinetik's own data: kegiatan, how many are done
 * and sent, and the numeric capaian against the target. It sits next to the SKP so the atasan rates with the
 * evidence in front of them.
 */
class SkpEvidence
{
    /** @return list<array{rk: string, kegiatan: int, selesai: int, terkirim: int, target: float|null, capaian: float|null, unit: string|null, catatan: list<string>}> */
    public function forQuarter(SkpStatus $skp): array
    {
        if ($skp->kind !== 'quarter' || $skp->quarter < 1) {
            return [];
        }

        $start = CarbonImmutable::create($skp->tahun, ($skp->quarter - 1) * 3 + 1, 1)->startOfDay();
        $end = $start->addMonths(3)->subDay();

        return PlanItem::query()->with('performancePlan:id,description')
            ->where('employee_id', $skp->employee_id)->where('status', '!=', 'cancelled')
            ->whereBetween('date_start', [$start->toDateString(), $end->toDateString()])
            ->get()->groupBy('performance_plan_id')
            ->map(function ($items) {
                $unit = $items->pluck('target_unit')->filter()->first();
                $target = $items->whereNotNull('target')->sum(fn (PlanItem $i) => (float) $i->target);
                $capaian = $items->whereNotNull('capaian')->sum(fn (PlanItem $i) => (float) $i->capaian);

                return [
                    'rk' => (string) ($items->first()->performancePlan?->description ?? 'RK tanpa nama'),
                    'kegiatan' => $items->count(),
                    'selesai' => $items->where('status', 'done')->count(),
                    'terkirim' => $items->whereNotNull('kip_sent_at')->count(),
                    'target' => $items->whereNotNull('target')->isEmpty() ? null : $target,
                    'capaian' => $items->whereNotNull('capaian')->isEmpty() ? null : $capaian,
                    'unit' => $unit,
                    'catatan' => $items->pluck('capaian_note')->filter()->take(3)->map(fn ($t) => mb_substr((string) $t, 0, 160))->values()->all(),
                ];
            })
            ->sortBy('rk')->values()->all();
    }
}
