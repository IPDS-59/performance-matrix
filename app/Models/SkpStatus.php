<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Where one SKP of a member stands in kipApp. Rows come from the daily sync and from the member's own sends. */
class SkpStatus extends Model
{
    /** Yearly SKP waiting for the atasan to set it. */
    public const YEARLY_WAITING = 2;

    /** Quarter SKP with the atasan to be rated. */
    public const QUARTER_WAITING = 5;

    protected $fillable = [
        'employee_id', 'tahun', 'kind', 'quarter', 'kip_skp_id', 'status_id', 'status_text',
        'atasan_pegawai_id', 'atasan_name', 'jml_rk', 'status_changed_at', 'synced_at',
    ];

    protected $casts = ['status_changed_at' => 'datetime', 'synced_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Store a SKP row of kipApp (a row of GET /skp) for an employee. @param  array<string, mixed>  $skp */
    public static function remember(int $employeeId, int $tahun, string $kind, int $quarter, array $skp, ?int $jmlRk = null): self
    {
        $row = static::firstOrNew(['employee_id' => $employeeId, 'tahun' => $tahun, 'kind' => $kind, 'quarter' => $quarter]);
        $statusId = is_numeric($skp['statusskpid'] ?? null) ? (int) $skp['statusskpid'] : null;
        if ($row->exists && $row->status_id !== $statusId) {
            $row->status_changed_at = now();
        }
        $row->fill([
            'kip_skp_id' => (string) $skp['id'],
            'status_id' => $statusId,
            'status_text' => isset($skp['statusskp']) ? mb_substr((string) $skp['statusskp'], 0, 100) : null,
            'atasan_pegawai_id' => filled($skp['atasanid'] ?? null) ? (string) $skp['atasanid'] : $row->atasan_pegawai_id,
            'atasan_name' => filled($skp['namaatasan'] ?? null) ? mb_substr((string) $skp['namaatasan'], 0, 150) : $row->atasan_name,
            'jml_rk' => $jmlRk ?? (int) ($skp['jmlrk'] ?? $row->jml_rk ?? 0),
            'synced_at' => now(),
        ]);
        $row->status_changed_at ??= now();
        $row->save();

        return $row;
    }

    /** Whether this row waits for the atasan to act. */
    public function isWaiting(): bool
    {
        return $this->kind === 'yearly' ? $this->status_id === self::YEARLY_WAITING : $this->status_id === self::QUARTER_WAITING;
    }

    public function label(): string
    {
        return $this->kind === 'yearly' ? 'SKP Tahunan '.$this->tahun : 'SKP TW '.$this->quarter.' '.$this->tahun;
    }
}
