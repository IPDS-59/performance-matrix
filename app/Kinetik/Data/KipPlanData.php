<?php

namespace App\Kinetik\Data;

/**
 * Represents a single Rencana Kinerja (RK) entry fetched from kipApp.
 *
 * Field mappings are DEFENSIVE: multiple candidate keys are tried because the
 * exact field names have not been confirmed against the official API spec.
 *
 * TODO(hardcopy): Confirm ALL of the following against the official kipApp API spec:
 *   - Plan ID key: currently tries [ 'id', 'rk_id', 'rkid', 'rencana_id', 'rencana_kinerja_id' ]
 *   - Plan name key: currently tries [ 'nama_rencana', 'uraian_rk', 'uraian', 'name', 'nama', 'deskripsi' ]
 */
readonly class KipPlanData
{
    public function __construct(
        public string $externalId,
        public string $name,
        public array $raw,
    ) {}

    public static function fromApiRow(array $row): self
    {
        return new self(
            externalId: self::pick($row, ['id', 'rk_id', 'rkid', 'rencana_id', 'rencana_kinerja_id']) ?? '',
            name: self::pick($row, ['nama_rencana', 'uraian_rk', 'uraian', 'name', 'nama', 'deskripsi']) ?? '',
            raw: $row,
        );
    }

    /**
     * Try each candidate key in order; return the first non-null value found, or null.
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
