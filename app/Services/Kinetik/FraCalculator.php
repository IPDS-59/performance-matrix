<?php

namespace App\Services\Kinetik;

use App\Models\FraIndicator;

/**
 * The arithmetic of the Kertas Kerja LK_Prov sheet, rewritten from its
 * formulas. "null" stands for the "-" the sheet shows.
 */
class FraCalculator
{
    public const MAX_CAPAIAN = 120;

    /** Value of an indicator: X over Y times 100 for percent rows, else the entered value. */
    public function value(FraIndicator $indicator, ?float $x, ?float $y): float
    {
        if ($indicator->isPercent()) {
            return $y ? ($x ?? 0) / $y * 100 : 0.0;
        }

        return $x ?? 0.0;
    }

    /** Capaian against a target: 120 when there is no target but a result, "-" when there is no result. */
    public function capaian(float $target, float $realization): ?float
    {
        if ($target == 0 && $realization > 0) {
            return (float) self::MAX_CAPAIAN;
        }
        if ($target == 0 || $realization <= 0) {
            return null;
        }

        return min($realization / $target * 100, self::MAX_CAPAIAN);
    }

    /** Predikat of the SAKIP score (cell E5). */
    public function sakipPredicate(?float $score): string
    {
        return match (true) {
            $score === null => 'D/Sangat Kurang',
            $score > 90 => 'AA/Sangat Memuaskan',
            $score > 80 => 'A/Memuaskan',
            $score > 70 => 'BB/Sangat Baik',
            $score > 60 => 'B/Baik',
            $score > 50 => 'CC/Cukup (Memadai)',
            $score > 30 => 'C/Kurang',
            default => 'D/Sangat Kurang',
        };
    }

    /** Correction share (0 to 0.3) by SAKIP predikat (column AK). */
    public function sakipCorrection(string $predicate): float
    {
        return match ($predicate) {
            'AA/Sangat Memuaskan', 'A/Memuaskan' => 0.0,
            'BB/Sangat Baik' => 0.10,
            'B/Baik' => 0.15,
            'CC/Cukup (Memadai)' => 0.20,
            default => 0.30,
        };
    }

    /** Predikat PKO of the average final capaian (cell AL102). */
    public function pkoPredicate(float $average): string
    {
        return match (true) {
            $average > 100 => 'ISTIMEWA',
            $average > 80 => 'BAIK',
            $average > 60 => 'BUTUH PERBAIKAN',
            default => 'BURUK',
        };
    }

    /**
     * @param  list<float|null>  $values
     */
    public function average(array $values, bool $skipZero = false): ?float
    {
        $numbers = array_values(array_filter($values, fn ($v) => $v !== null && (! $skipZero || $v != 0)));

        return $numbers ? array_sum($numbers) / count($numbers) : null;
    }
}
