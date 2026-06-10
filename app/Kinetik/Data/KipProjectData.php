<?php

namespace App\Kinetik\Data;

use Illuminate\Support\Collection;

readonly class KipProjectData
{
    /**
     * @param  Collection<int, KipMemberData>  $members
     */
    public function __construct(
        public string $externalId,
        public string $name,
        public string $teamExternalId,
        public ?string $teamName,
        public ?string $leaderNipLama,
        public ?string $leaderName,
        public Collection $members,
        public array $raw,
    ) {}

    /**
     * Build from a `proyek?timkerjaid=` row. Each row is one project, carrying
     * its team identity, the team leader, and the project's members (`anggota`).
     */
    public static function fromApiRow(array $row): self
    {
        $members = collect($row['anggota'] ?? [])
            ->map(fn (array $m) => KipMemberData::fromApiRow($m))
            ->values();

        return new self(
            externalId: self::pick($row, ['proyekid']) ?? '',
            name: trim(self::pick($row, ['namaproyek']) ?? ''),
            teamExternalId: self::pick($row, ['timkerjaid']) ?? '',
            teamName: self::pick($row, ['namatim']),
            leaderNipLama: self::pick($row, ['niplamaketua']),
            leaderName: self::pick($row, ['namaketua']),
            members: $members,
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
