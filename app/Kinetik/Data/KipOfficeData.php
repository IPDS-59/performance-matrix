<?php

namespace App\Kinetik\Data;

/**
 * One place an employee works, from kipApp v1/pegawai/lokasi.
 */
readonly class KipOfficeData
{
    public function __construct(
        public string $wilayahId,
        public string $wilayahName,
        public string $unitKerjaId,
        public string $unitKerjaName,
    ) {}

    /**
     * @param  array<string, mixed>  $json  full lokasi response
     * @return list<self>
     */
    public static function listFromLokasi(array $json): array
    {
        $out = [];
        foreach ($json['wilayah'] ?? [] as $wilayah) {
            foreach ($wilayah['unitkerja'] ?? [] as $unit) {
                $out[] = new self(
                    (string) ($wilayah['id'] ?? ''),
                    (string) ($wilayah['wilayah'] ?? ''),
                    (string) ($unit['id'] ?? ''),
                    (string) ($unit['unitkerja'] ?? ''),
                );
            }
        }

        return $out;
    }

    public function label(): string
    {
        return trim("{$this->unitKerjaName} {$this->wilayahName}");
    }
}
