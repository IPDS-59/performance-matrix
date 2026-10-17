<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The kipApp status of a member's quarter SKP (statusskpid): 1 "Sedang dibuat", 6 returned to the
 * member, others mean it is with the atasan or rated. Unknown (no row, no id) never locks anything.
 */
class MemberSkpStatus extends Model
{
    protected $fillable = ['employee_id', 'tahun', 'quarter', 'status_id', 'status_text'];

    public static function remember(int $employeeId, int $tahun, int $quarter, mixed $statusId, mixed $statusText): void
    {
        static::updateOrCreate(
            ['employee_id' => $employeeId, 'tahun' => $tahun, 'quarter' => $quarter],
            ['status_id' => is_numeric($statusId) ? (int) $statusId : null, 'status_text' => $statusText !== null ? mb_substr((string) $statusText, 0, 100) : null],
        );
    }

    /** The status id of the quarter, or null when unknown. */
    public static function idFor(int $employeeId, int $tahun, int $quarter): ?int
    {
        return static::where(['employee_id' => $employeeId, 'tahun' => $tahun, 'quarter' => $quarter])->value('status_id');
    }
}
