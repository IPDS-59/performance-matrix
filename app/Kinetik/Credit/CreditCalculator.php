<?php

namespace App\Kinetik\Credit;

use App\Models\Employee;
use App\Models\EmployeeCareer;
use App\Models\KipPerformanceRating;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Angka Kredit progress of one employee, computed on read from the synced
 * kipApp ratings (PerBKN 3/2023 Pasal 13, PermenPANRB 1/2023 Pasal 37):
 *
 *   AK of one month = yearly coefficient of that month's jenjang / 12 x predikat share
 *
 * Counting per month handles monthly SKPs (before 2026) and quarterly SKPs
 * alike, and a month covered by two SKPs counts once.
 */
class CreditCalculator
{
    /** Years in the current golongan before a promotion. */
    private const MIN_YEARS_IN_GOLONGAN = 2;

    /** "Hampir": at most this many quarters at Baik to go. */
    private const NEAR_QUARTERS = 2;

    /**
     * @return array<string, mixed>
     */
    public function forEmployee(Employee $employee, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $career = $employee->career;
        $base = [
            'employee_id' => $employee->id,
            'name' => $employee->display_name ?? $employee->name,
            'jabatan' => $career?->jabatan,
            'golongan' => $career?->golongan,
            'pangkat' => $career?->pangkat,
        ];

        if ($career === null || $career->golongan === null) {
            return [...$base, 'status' => 'no_data'];
        }

        $level = FunctionalLevel::fromJabatan($career->jabatan);
        if ($level === null) {
            return [...$base, 'status' => 'non_jf'];
        }

        $step = $this->nextStep($career, $level);
        $countedFrom = $career->ak_base_date
            ? CarbonImmutable::parse($career->ak_base_date)->addDay()
            : ($step['from'] ? CarbonImmutable::parse($step['from']) : null);

        if ($countedFrom === null) {
            return [...$base, 'status' => 'no_data', 'level' => $level->value];
        }

        $months = $this->months($employee->performanceRatings, $today)
            ->filter(fn (array $m) => $m['month'] >= $countedFrom->startOfMonth()->format('Y-m'));

        $earned = round(($career->ak_base ?? 0) + $months->sum('ak'), 3);
        $estimated = round($months->where('final', false)->sum('ak'), 3);
        $quarterAtBaik = $level->yearlyCoefficient() / 4;
        $gap = $step['target'] === null ? null : max(0, round($step['target'] - $earned, 3));
        $eligibleFrom = $career->golongan_since
            ? CarbonImmutable::parse($career->golongan_since)->addYears(self::MIN_YEARS_IN_GOLONGAN)
            : null;

        return [
            ...$base,
            'level' => $level->value,
            'coefficient' => $level->yearlyCoefficient(),
            'kind' => $step['kind'],
            'next_label' => $step['next'],
            'target' => $step['target'],
            'earned' => $earned,
            'estimated' => $estimated,
            'gap' => $gap,
            'quarters_to_go' => $gap === null ? null : (int) ceil($gap / $quarterAtBaik),
            'counted_from' => $countedFrom->toDateString(),
            'golongan_since' => $career->golongan_since?->toDateString(),
            'eligible_from' => $eligibleFrom?->toDateString(),
            'ak_base' => $career->ak_base,
            'ak_base_date' => $career->ak_base_date?->toDateString(),
            'status' => $this->status($step['target'], $earned, $gap, $quarterAtBaik, $eligibleFrom, $today),
            'quarters' => $this->quarters($months),
        ];
    }

    /**
     * Next pangkat inside the jenjang, or the next jenjang from its top golongan.
     * The jenjang target is proportional when the jenjang started above its
     * lowest golongan (footnote of Lampiran A).
     *
     * @return array{kind: string|null, next: string|null, target: float|null, from: mixed}
     */
    private function nextStep(EmployeeCareer $career, FunctionalLevel $level): array
    {
        $ranks = $level->golongan();
        $index = array_search($career->golongan, $ranks, true);

        if ($index !== false && $index < count($ranks) - 1) {
            return ['kind' => 'pangkat', 'next' => $ranks[$index + 1], 'target' => $level->pangkatTarget(), 'from' => $career->golongan_since];
        }

        $next = $level->nextLevel();
        if ($next === null || $level->jenjangTarget() === null) {
            return ['kind' => null, 'next' => null, 'target' => null, 'from' => $career->golongan_since];
        }

        $entry = array_search($career->level_start_golongan, $ranks, true);
        $share = (count($ranks) - ($entry === false ? 0 : $entry)) / count($ranks);

        return ['kind' => 'jenjang', 'next' => $next->value, 'target' => round($level->jenjangTarget() * $share, 3), 'from' => $career->level_since];
    }

    /**
     * One entry per covered month up to today. A final rating wins over an
     * unfinished one for the same month. Without a predikat yet, the month is
     * an estimate at Baik.
     *
     * @param  Collection<int, KipPerformanceRating>  $ratings
     * @return Collection<string, array{month: string, ak: float, predikat: string|null, final: bool}>
     */
    private function months(Collection $ratings, CarbonImmutable $today): Collection
    {
        $months = collect();

        foreach ($ratings->sortBy('period_start') as $rating) {
            $share = FunctionalLevel::predikatShare($rating->predikat);
            $final = $share !== null && $rating->status === 'Dinilai';
            $level = FunctionalLevel::fromJabatan($rating->jabatan);
            $monthAk = $level ? $level->yearlyCoefficient() / 12 * ($share ?? 1.0) : 0.0;

            $cursor = CarbonImmutable::parse($rating->period_start)->startOfMonth();
            $end = CarbonImmutable::parse($rating->period_end);
            while ($cursor <= $end && $cursor <= $today) {
                $key = $cursor->format('Y-m');
                if (! $months->has($key) || (! $months[$key]['final'] && $final)) {
                    $months[$key] = ['month' => $key, 'ak' => $monthAk, 'predikat' => $rating->predikat, 'final' => $final];
                }
                $cursor = $cursor->addMonth();
            }
        }

        return $months->sortKeys();
    }

    /**
     * @param  Collection<string, array{month: string, ak: float, predikat: string|null, final: bool}>  $months
     * @return list<array{label: string, ak: float, predikat: string|null, final: bool}>
     */
    private function quarters(Collection $months): array
    {
        return $months
            ->groupBy(fn (array $m) => substr($m['month'], 0, 4).'-'.intdiv((int) substr($m['month'], 5, 2) - 1, 3))
            ->map(fn (Collection $group, string $key) => [
                'label' => 'TW '.['I', 'II', 'III', 'IV'][(int) substr($key, 5)].' '.substr($key, 0, 4),
                'ak' => round($group->sum('ak'), 3),
                'predikat' => $group->pluck('predikat')->filter()->unique()->implode(' / ') ?: null,
                'final' => $group->every(fn (array $m) => $m['final']),
            ])
            ->reverse()
            ->values()
            ->all();
    }

    private function status(?float $target, float $earned, ?float $gap, float $quarterAtBaik, ?CarbonImmutable $eligibleFrom, CarbonImmutable $today): string
    {
        if ($target === null) {
            return 'top';
        }
        if ($earned >= $target) {
            return $eligibleFrom !== null && $today->lt($eligibleFrom) ? 'ak_ready' : 'ready';
        }

        return $gap <= $quarterAtBaik * self::NEAR_QUARTERS ? 'near' : 'progress';
    }
}
