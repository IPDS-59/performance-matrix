<?php

namespace App\Kinetik\Data;

readonly class KipTeamData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public array $raw,
    ) {}

    /**
     * Build from a team row of monitoring/hirarki/daerah (`id`, `namaTim`) or a
     * `timkerja` node of the pegawai/lokasi tree (`id`, `timkerja`).
     */
    public static function fromApiRow(array $row): self
    {
        return new self(
            externalId: self::pick($row, ['id', 'timkerjaid']) ?? '',
            name: self::pick($row, ['namaTim', 'timkerja', 'namatim']) ?? '',
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
