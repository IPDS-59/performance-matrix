<?php

namespace App\Kinetik\Data;

/**
 * Represents a single unsent daily activity fetched from kipApp.
 *
 * Field mappings are confirmed from live API capture on 2026-06-04 (niplama 340054274).
 * Primary keys are from the real response; one fallback is kept per date/time field.
 *
 * TODO(hardcopy): Still open:
 *   - (a) Official server-to-server auth mechanism (currently uses browser-captured session JWT).
 *   - (b) Populated shape of rkpegawai / rktimkerja (endpoint returned `{jumlahrk:0}` during capture).
 */
readonly class KipActivityData
{
    public function __construct(
        public string $externalId,
        public string $description,
        public string $dateStart,
        public ?string $dateEnd,
        public ?string $timeStart,
        public ?string $timeEnd,
        public ?string $evidenceUrl,
        public ?string $rkExternalId,
        public ?string $rkName,
        public ?int $progress,
        public ?string $achievementNote,
        public ?string $sentAt,
        public ?string $periodId,
        public ?int $sourceYear,
        public array $raw,
    ) {}

    public static function fromApiRow(array $row): self
    {
        return new self(
            externalId: self::pick($row, ['kegiatanperhariid', 'kegiatan_id']) ?? '',
            description: self::pick($row, ['kegiatan', 'uraian_kegiatan']) ?? '',
            dateStart: self::pick($row, ['tanggal', 'tgl_mulai']) ?? '',
            dateEnd: self::pick($row, ['tanggalselesai', 'tgl_selesai']),
            timeStart: self::pick($row, ['jammulai', 'jam_mulai']),
            timeEnd: self::pick($row, ['jamselesai', 'jam_selesai']),
            evidenceUrl: self::pick($row, ['datadukung', 'bukti_dukung']),
            rkExternalId: self::pick($row, ['rkid']),
            rkName: self::pick($row, ['rencanakinerja']),
            progress: isset($row['progres']) ? (int) $row['progres'] : null,
            achievementNote: self::pick($row, ['capaian']),
            sentAt: array_key_exists('tanggalkirim', $row) ? ($row['tanggalkirim'] !== null ? (string) $row['tanggalkirim'] : null) : null,
            periodId: self::pick($row, ['periodeid']),
            sourceYear: isset($row['tahun']) ? (int) $row['tahun'] : null,
            raw: $row,
        );
    }

    /**
     * Try each candidate key in order; return the first non-null, non-empty value, or null.
     *
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
