<?php

namespace App\Kinetik\Data;

readonly class KipMemberData
{
    public function __construct(
        public ?string $anggotaId,
        public ?string $pegawaiId,
        public string $nipLama,
        public ?string $nipBaru,
        public string $name,
        public ?string $jabatanId,
        public ?string $jabatanName,
        public array $raw,
    ) {}

    public static function fromApiRow(array $row): self
    {
        return new self(
            anggotaId: self::pick($row, ['anggotaid', 'anggotatimid']),
            pegawaiId: self::pick($row, ['pegawaiid']),
            nipLama: self::pick($row, ['niplama']) ?? '',
            nipBaru: self::pick($row, ['nipbaru']),
            name: self::pick($row, ['nama', 'namaanggota']) ?? '',
            jabatanId: self::pick($row, ['jabatanid']),
            jabatanName: self::pick($row, ['namajabatan']),
            raw: $row,
        );
    }

    /**
     * @param  string[]  $keys
     */
    private static function pick(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return (string) $row[$key];
            }
        }

        return null;
    }
}
