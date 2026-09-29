<?php

namespace App\Kinetik\Data;

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

        return new self(
            skpId: (string) $row['id'],
            periodStart: (string) $row['periodeawal'],
            periodEnd: (string) $row['periodeakhir'],
            jabatan: filled($row['namajabatan'] ?? null) ? (string) $row['namajabatan'] : null,
            predikat: filled($row['predikat'] ?? null) ? (string) $row['predikat'] : null,
            nilaiPrestasi: is_numeric($row['nilaiprestasi'] ?? null) ? (float) $row['nilaiprestasi'] : null,
            status: filled($row['statusskp'] ?? null) ? (string) $row['statusskp'] : null,
        );
    }
}
