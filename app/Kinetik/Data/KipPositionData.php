<?php

namespace App\Kinetik\Data;

/**
 * One position record of an employee, from kipApp v1/pegawai?niplama=.
 * A new record starts on each move, promotion or new jabatan.
 */
readonly class KipPositionData
{
    public function __construct(
        public string $pegawaiId,
        public ?string $tmt,
        public ?string $jabatan,
        public ?string $golongan,
        public ?string $pangkat,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromApiRow(array $row): self
    {
        return new self(
            pegawaiId: (string) ($row['id'] ?? ''),
            tmt: filled($row['tmt'] ?? null) ? (string) $row['tmt'] : null,
            jabatan: filled($row['nama_jabatan'] ?? null) ? (string) $row['nama_jabatan'] : null,
            golongan: self::golongan($row['golongan'] ?? null),
            pangkat: filled($row['pangkat'] ?? null) ? (string) $row['pangkat'] : null,
        );
    }

    /**
     * kipApp sometimes pads golongan ("IV/a "); keep the plain form "IV/a".
     */
    public static function golongan(mixed $value): ?string
    {
        $clean = preg_replace('/\s+/', '', (string) $value);

        return $clean === '' ? null : $clean;
    }
}
