<?php

namespace App\Kinetik\Data;

use Carbon\CarbonImmutable;

/**
 * One periodic SKP rating from kipApp v1/skp?jenis=2.
 */
readonly class KipRatingData
{
    public function __construct(
        public string $skpId,
        public string $periodStart,
        public string $periodEnd,
        public ?string $jabatan,
        public ?string $predikat,
        public ?float $nilaiPrestasi,
        public ?string $status,
    ) {}

    /**
     * Null when the row has no period dates.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromApiRow(array $row): ?self
    {
        if (blank($row['id'] ?? null) || blank($row['periodeawal'] ?? null) || blank($row['periodeakhir'] ?? null)) {
            return null;
        }

        [$start, $end] = self::period($row);

        return new self(
            skpId: (string) $row['id'],
            periodStart: $start,
            periodEnd: $end,
            jabatan: filled($row['namajabatan'] ?? null) ? (string) $row['namajabatan'] : null,
            predikat: filled($row['predikat'] ?? null) ? (string) $row['predikat'] : null,
            nilaiPrestasi: is_numeric($row['nilaiprestasi'] ?? null) ? (float) $row['nilaiprestasi'] : null,
            status: filled($row['statusskp'] ?? null) ? (string) $row['statusskp'] : null,
        );
    }

    /**
     * The month or quarter the rating is for. Monthly SKPs before 2026 carry
     * the whole year in periodeawal/periodeakhir, so the real period comes
     * from tahun + jenisperiodepenilaian (1 = month, 2 = quarter) +
     * namaperiodepenilaian (its number).
     *
     * @param  array<string, mixed>  $row
     * @return array{0: string, 1: string}
     */
    private static function period(array $row): array
    {
        $year = (int) ($row['tahun'] ?? 0);
        $number = (int) ($row['namaperiodepenilaian'] ?? 0);
        $months = match ((int) ($row['jenisperiodepenilaian'] ?? 0)) {
            1 => 1,
            2 => 3,
            default => 0,
        };

        if ($year < 2000 || $months === 0 || $number < 1 || $number > 12 / $months) {
            return [(string) $row['periodeawal'], (string) $row['periodeakhir']];
        }

        $start = CarbonImmutable::create($year, ($number - 1) * $months + 1, 1);

        return [$start->toDateString(), $start->addMonths($months - 1)->endOfMonth()->toDateString()];
    }
}
