<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PlanItem;
use Carbon\Carbon;

/**
 * Finds a plan item's kegiatan row in kipApp. Items Kinetik pushed know their SKP (kip_skp_id); items that came in from a
 * kipApp sync do not, and looking in "SKP ''" made every edit fail with "no longer exists in kipApp" although the
 * kegiatan was there. So without a stored SKP the quarter SKP of the date is searched first, then the other quarters,
 * and the SKP found is remembered on the item.
 */
class LocatePlanItemActivityAction
{
    /** @return array{skp_id: string, row: array<string, mixed>}|null */
    public function execute(ApiKipWriter $writer, PlanItem $plan): ?array
    {
        $wanted = (string) $plan->kip_external_id;
        $find = fn (string $skpId) => collect($writer->activities($skpId))->first(fn (array $r) => (string) $r['kegiatanperhariid'] === $wanted);

        if (filled($plan->kip_skp_id) && ($row = $find((string) $plan->kip_skp_id)) !== null) {
            return ['skp_id' => (string) $plan->kip_skp_id, 'row' => $row];
        }

        $pegawaiId = (string) $plan->employee?->kip_pegawai_id;
        if ($pegawaiId === '') {
            return null;
        }
        $periodeId = (int) config('kinetik.kip.periode_id');
        $firstQuarter = Carbon::parse($plan->date_start)->quarter;
        $quarters = collect([$firstQuarter, 1, 2, 3, 4])->unique();

        foreach ($quarters as $quarter) {
            $skp = $writer->quarterSkp($pegawaiId, $periodeId, (int) $quarter);
            if ($skp !== null && ($row = $find((string) $skp['id'])) !== null) {
                $plan->forceFill(['kip_skp_id' => (string) $skp['id']])->save();

                return ['skp_id' => (string) $skp['id'], 'row' => $row];
            }
        }

        return null;
    }
}
