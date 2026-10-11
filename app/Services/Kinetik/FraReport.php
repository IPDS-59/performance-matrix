<?php

namespace App\Services\Kinetik;

use App\Models\FraIndicator;
use App\Models\FraQuarterValue;
use Illuminate\Support\Collection;

/** Everything the LK_Prov page and export need for one year, computed like the sheet does. */
class FraReport
{
    public function __construct(private readonly FraCalculator $calc) {}

    /**
     * @param  Collection<int, FraIndicator>  $indicators  with quarterValues loaded
     * @return array{indicators: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function build(Collection $indicators, int $quarter, ?float $sakipScore = null): array
    {
        $predicate = $this->calc->sakipPredicate($sakipScore);
        $correction = $this->calc->sakipCorrection($predicate);

        $rows = $indicators->sortBy('sort_order')->values()->map(function (FraIndicator $ind) use ($quarter, $correction) {
            $yearTarget = $this->calc->value($ind, $ind->target_x, $ind->target_y);
            $byQuarter = $ind->quarterValues->keyBy('quarter');

            $quarters = [];
            foreach ([1, 2, 3, 4] as $q) {
                /** @var FraQuarterValue|null $v */
                $v = $byQuarter->get($q);
                $allocation = $this->calc->value($ind, $v?->allocation_x, $v?->allocation_y);
                $realization = $this->calc->value($ind, $v?->realization_x, $v?->realization_y);
                $quarters[$q] = [
                    'allocation_value' => $allocation,
                    'realization_value' => $realization,
                    'capaian_quarter' => $this->calc->capaian($allocation, $realization),
                    'capaian_year' => $this->calc->capaian($yearTarget, $realization),
                ];
            }

            // The final score of the indicator uses the capaian against the year target, capped at 110.
            $now = $quarters[$quarter]['capaian_year'];
            $normalized = $now === null ? null : min($now, 110.0);

            return [
                ...$ind->only(['id', 'sort_order', 'tujuan', 'sasaran_code', 'sasaran_name', 'code', 'name', 'kind', 'period_type', 'unit_type', 'percent_label', 'unit', 'x_label', 'y_label', 'target_x', 'target_y', 'owner_team_id']),
                'target_value' => $yearTarget,
                'quarters' => $quarters,
                'values' => $byQuarter->map(fn (FraQuarterValue $v) => $v->only(['allocation_x', 'allocation_y', 'realization_x', 'realization_y', 'obstacle', 'solution', 'follow_up', 'pic', 'deadline', 'evidence_url', 'previous_follow_up_url']))->all(),
                'normalized' => $normalized,
                'correction' => $correction,
                'final' => $normalized === null ? null : $normalized * (1 - $correction),
            ];
        })->all();

        $iku = array_filter($rows, fn ($r) => $r['kind'] === 'IKU');
        $finals = array_column($rows, 'final');
        $nko = $this->calc->average($finals);

        $sasaran = [];
        foreach (collect($iku)->groupBy('sasaran_code') as $code => $group) {
            $sasaran[] = [
                'code' => $code,
                'name' => $group->first()['sasaran_name'],
                'capaian_year' => $this->calc->average($group->map(fn ($r) => $r['quarters'][$quarter]['capaian_year'])->all()),
            ];
        }

        return [
            'indicators' => $rows,
            'summary' => [
                'quarter' => $quarter,
                'sakip_score' => $sakipScore,
                'sakip_predicate' => $predicate,
                'iku_capaian_quarter' => $this->calc->average(array_map(fn ($r) => $r['quarters'][$quarter]['capaian_quarter'], $iku), skipZero: true),
                'iku_capaian_year' => $this->calc->average(array_map(fn ($r) => $r['quarters'][$quarter]['capaian_year'], $iku)),
                'sasaran' => $sasaran,
                'nko' => $nko,
                'pko_predicate' => $nko === null ? null : $this->calc->pkoPredicate($nko),
            ],
        ];
    }
}
