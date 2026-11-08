<?php

namespace App\Services\Kinetik;

use App\Models\FraIndicator;
use App\Models\FraMonthValue;
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
    public function build(Collection $indicators, int $quarter, ?float $sakipScore = null, ?int $month = null): array
    {
        $month ??= $quarter * 3;
        $predicate = $this->calc->sakipPredicate($sakipScore);
        $correction = $this->calc->sakipCorrection($predicate);

        $rows = $indicators->sortBy('sort_order')->values()->map(function (FraIndicator $ind) use ($month, $correction) {
            $yearTarget = $this->calc->value($ind, $ind->target_x, $ind->target_y);
            $byQuarter = $ind->quarterValues->keyBy('quarter');
            $byMonth = $ind->monthValues->keyBy('month');

            $quarters = [];
            foreach ([1, 2, 3, 4] as $q) {
                /** @var FraQuarterValue|null $v */
                $v = $byQuarter->get($q);
                $allocation = $this->calc->value($ind, $v?->allocation_x, $v?->allocation_y);
                // Months entered up to the end of the quarter win over the quarter figures typed by hand.
                [$realX, $realY] = $this->fromMonths($ind, $byMonth, $q * 3) ?? [$v?->realization_x, $v?->realization_y];
                $realization = $this->calc->value($ind, $realX, $realY);
                $quarters[$q] = [
                    'allocation_value' => $allocation,
                    'realization_value' => $realization,
                    'capaian_quarter' => $this->calc->capaian($allocation, $realization),
                    'capaian_year' => $this->calc->capaian($yearTarget, $realization),
                ];
            }

            // Realisation up to the chosen month; before any month is entered, the quarter figure typed by hand.
            $cum = $this->fromMonths($ind, $byMonth, $month) ?? [$byQuarter->get((int) ceil($month / 3))?->realization_x, $byQuarter->get((int) ceil($month / 3))?->realization_y];
            $now = $this->calc->capaian($yearTarget, $this->calc->value($ind, $cum[0], $cum[1]));
            $months = [];
            for ($m = 1; $m <= 12; $m++) {
                $upTo = $this->fromMonths($ind, $byMonth, $m);
                $months[$m] = [
                    ...($byMonth->get($m)?->only(['x', 'y', 'obstacle', 'solution', 'follow_up', 'pic', 'deadline', 'evidence_url', 'previous_follow_up_url']) ?? []),
                    'value' => $byMonth->get($m) ? $this->calc->value($ind, $byMonth->get($m)->x, $byMonth->get($m)->y) : null,
                    'cum_value' => $upTo === null ? null : $this->calc->value($ind, $upTo[0], $upTo[1]),
                ];
            }
            // The LK_Prov export is quarterly, so a quarter's analysis is built from its three months (see quarterAnalysis).
            $analysis = [];
            foreach ([1, 2, 3, 4] as $q) {
                $analysis[$q] = $this->quarterAnalysis($byQuarter->get($q), $byMonth, $q);
            }
            $normalized = $now === null ? null : min($now, 110.0);

            return [
                ...$ind->only(['id', 'sort_order', 'tujuan', 'sasaran_code', 'sasaran_name', 'code', 'name', 'kind', 'period_type', 'unit_type', 'percent_label', 'unit', 'x_label', 'y_label', 'target_x', 'target_y', 'owner_team_id']),
                'target_value' => $yearTarget,
                'quarters' => $quarters,
                'months' => $months,
                'cum_capaian_year' => $now,
                'values' => $byQuarter->map(fn (FraQuarterValue $v) => [...$v->only(['allocation_x', 'allocation_y', 'realization_x', 'realization_y']), ...$analysis[$v->quarter]])->all(),
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
                'capaian_year' => $this->calc->average($group->map(fn ($r) => $r['cum_capaian_year'])->all()),
            ];
        }

        return [
            'indicators' => $rows,
            'summary' => [
                'quarter' => $quarter,
                'sakip_score' => $sakipScore,
                'sakip_predicate' => $predicate,
                'month' => $month,
                'iku_capaian_quarter' => $this->calc->average(array_map(fn ($r) => $r['quarters'][$quarter]['capaian_quarter'], $iku), skipZero: true),
                'iku_capaian_year' => $this->calc->average(array_map(fn ($r) => $r['cum_capaian_year'], $iku)),
                'sasaran' => $sasaran,
                'nko' => $nko,
                'pko_predicate' => $nko === null ? null : $this->calc->pkoPredicate($nko),
            ],
        ];
    }

    private const MONTH_ABBR = [1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'];

    /**
     * The analysis of a quarter, cascaded from its months. Kendala, solusi and RTL list each month that wrote one ("Jan: …", "Feb: …"),
     * so nothing written in the first two months is lost. PIC and deadline are the latest month's, and the evidence links of all months
     * are kept. What was typed on the quarter itself only fills a field that no month wrote.
     *
     * @param  Collection<int, FraMonthValue>  $byMonth
     * @return array<string, ?string>
     */
    private function quarterAnalysis(?FraQuarterValue $own, Collection $byMonth, int $quarter): array
    {
        $months = collect(range($quarter * 3 - 2, $quarter * 3))->map(fn (int $m) => [$m, $byMonth->get($m)])->filter(fn (array $p) => $p[1] !== null);
        $ownAnalysis = $own?->only(['obstacle', 'solution', 'follow_up', 'pic', 'deadline', 'evidence_url', 'previous_follow_up_url']) ?? [];

        // A problem that comes back every month is listed once, with the months it was written in ("Jan–Mar: …", "Jan, Mar: …").
        $listed = function (string $field) use ($months) {
            $groups = [];
            foreach ($months->filter(fn (array $p) => filled($p[1]->{$field})) as [$m, $row]) {
                $text = trim((string) $row->{$field});
                $key = mb_strtolower(preg_replace('/\s+/', ' ', $text));
                $groups[$key]['text'] ??= $text;
                $groups[$key]['months'][] = $m;
            }

            return collect($groups)->map(function (array $g) {
                $first = $g['months'][0];
                $last = end($g['months']);
                $consecutive = $last - $first + 1 === count($g['months']);
                $label = count($g['months']) === 1 ? self::MONTH_ABBR[$first] : ($consecutive ? self::MONTH_ABBR[$first].'–'.self::MONTH_ABBR[$last] : collect($g['months'])->map(fn (int $m) => self::MONTH_ABBR[$m])->implode(', '));

                return $label.': '.$g['text'];
            })->implode("\n");
        };
        $latest = fn (string $field) => $months->map(fn (array $p) => $p[1]->{$field})->filter(fn ($v) => filled($v))->last();
        $links = fn (string $field) => $months->map(fn (array $p) => trim((string) $p[1]->{$field}))->filter()->unique()->implode("\n");

        return [
            'obstacle' => $listed('obstacle') ?: ($ownAnalysis['obstacle'] ?? null),
            'solution' => $listed('solution') ?: ($ownAnalysis['solution'] ?? null),
            'follow_up' => $listed('follow_up') ?: ($ownAnalysis['follow_up'] ?? null),
            'pic' => $latest('pic') ?? ($ownAnalysis['pic'] ?? null),
            'deadline' => $latest('deadline') ?? ($ownAnalysis['deadline'] ?? null),
            'evidence_url' => $links('evidence_url') ?: ($ownAnalysis['evidence_url'] ?? null),
            'previous_follow_up_url' => $links('previous_follow_up_url') ?: ($ownAnalysis['previous_follow_up_url'] ?? null),
        ];
    }

    /**
     * Realisation of the year so far, from the monthly figures up to $lastMonth: X and Y added up for a percent row; for
     * a value row the sum, or the average when the row is itself a percentage.
     *
     * @param  Collection<int, FraMonthValue>  $months
     * @return array{0: ?float, 1: ?float}|null null when no month is entered
     */
    private function fromMonths(FraIndicator $ind, Collection $months, int $lastMonth): ?array
    {
        $used = $months->filter(fn ($m) => $m->month <= $lastMonth && ($m->x !== null || $m->y !== null));
        if ($used->isEmpty()) {
            return null;
        }
        if ($ind->isPercent()) {
            return [$used->sum('x'), $used->sum('y')];
        }

        return [$ind->percent_label ? $used->avg('x') : $used->sum('x'), null];
    }
}
