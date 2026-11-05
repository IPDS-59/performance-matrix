<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Credit\CreditCalculator;
use App\Models\Employee;

/**
 * Sets the office's yearly AK (2023 to 2025) beside what Kinetik's own formula gives for the same year from the kipApp ratings,
 * so a person can see whether the formula, the jenjang dates and the ratings agree with the official figures, and why not.
 * The office gives one predikat per year, so "official_share" is the official figure divided by what the same months give at 100%.
 */
class CompareAkRekapanAction
{
    private const YEARS = [2023, 2024, 2025];

    public function __construct(private readonly CreditCalculator $calculator) {}

    /**
     * @param  list<array<string, mixed>>  $rows  nip, name, y2023, y2024, y2025
     * @return list<array<string, mixed>>
     */
    public function execute(array $rows): array
    {
        $employees = Employee::with('performanceRatings')->whereNotNull('nip_baru')->get()->keyBy(fn (Employee $e) => preg_replace('/\D/', '', (string) $e->nip_baru));

        return collect($rows)->map(function (array $row) use ($employees) {
            $nip = preg_replace('/\D/', '', (string) ($row['nip'] ?? ''));
            $employee = $employees->get($nip);
            $months = $employee ? $this->calculator->monthlyAk($employee) : collect();

            $years = collect(self::YEARS)->mapWithKeys(function (int $year) use ($row, $months) {
                $official = $row['y'.$year] ?? null;
                $inYear = $months->filter(fn (array $m) => str_starts_with($m['month'], (string) $year));
                $kinetik = $inYear->isEmpty() ? null : round($inYear->sum('ak'), 3);
                $atBaik = round($inYear->sum(fn (array $m) => $m['coefficient'] / 12), 3);
                $diff = ($official !== null && $kinetik !== null) ? round($kinetik - (float) $official, 3) : null;

                return [$year => [
                    'official' => $official,
                    'kinetik' => $kinetik,
                    'diff' => $diff,
                    'months' => $inYear->count(),
                    'official_share' => ($official !== null && $atBaik > 0) ? round((float) $official / $atBaik, 2) : null,
                    'predikat' => $inYear->groupBy(fn (array $m) => $m['predikat'] ?? 'belum dinilai')->map->count()->map(fn (int $n, string $p) => "{$p} {$n}")->values()->implode(', '),
                    'verdict' => $this->verdict($official, $kinetik, $diff, $inYear->count()),
                ]];
            });

            return ['nip' => $nip, 'name' => $row['name'] ?? null, 'employee_name' => $employee ? ($employee->display_name ?? $employee->name) : null, 'matched' => $employee !== null, 'years' => $years->all()];
        })->values()->all();
    }

    private function verdict(mixed $official, ?float $kinetik, ?float $diff, int $months): string
    {
        return match (true) {
            $official === null => 'no_official',
            $kinetik === null => 'no_kip',
            abs($diff) < 0.01 => 'same',
            $months < 12 && $diff < 0 => 'months_missing',
            default => 'predikat',
        };
    }
}
