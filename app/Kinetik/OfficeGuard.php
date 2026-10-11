<?php

namespace App\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipOfficeData;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;

/**
 * Kinetik only keeps staff of the configured office (BPS Provinsi Sulawesi
 * Tengah: KIP_WILAYAH_ID + KIP_UNITKERJA_ID). kipApp v1/pegawai/lokasi tells
 * where an employee works now; the answer is cached on the employee.
 */
class OfficeGuard
{
    private const CACHE_DAYS = 7;

    public function __construct(private readonly KipStructureSource $source) {}

    /**
     * True = works at our office, false = works elsewhere, null = unknown
     * (API failed or returned nothing). Unknown never removes anyone.
     */
    public function check(Employee $employee, bool $fresh = false): ?bool
    {
        if (blank($employee->nip_lama)) {
            return null;
        }

        $cached = ! $fresh
            && $employee->kip_office_checked_at !== null
            && $employee->kip_office_checked_at->gt(now()->subDays(self::CACHE_DAYS));
        if ($cached) {
            return $employee->kip_in_office;
        }

        try {
            $offices = $this->source->fetchEmployeeOffices((string) $employee->nip_lama);
        } catch (Throwable) {
            return null;
        }

        if ($offices === []) {
            return null;
        }

        $inOffice = collect($offices)->contains(fn (KipOfficeData $o) => $o->wilayahId === (string) config('kinetik.kip.wilayah_id')
            && $o->unitKerjaId === (string) config('kinetik.kip.unitkerja_id'));

        $employee->forceFill([
            'kip_office' => collect($offices)->map->label()->unique()->implode('; '),
            'kip_in_office' => $inOffice,
            'kip_office_checked_at' => now(),
        ])->save();

        return $inOffice;
    }

    /**
     * Remove someone who works elsewhere: inactive, out of every team and
     * project, no longer a team leader, and their login locked. Claims and
     * other history stay.
     */
    public function evict(Employee $employee): void
    {
        DB::transaction(function () use ($employee) {
            $employee->teams()->detach();
            $employee->projects()->detach();
            Team::where('leader_id', $employee->id)->update(['leader_id' => null]);

            $employee->forceFill(['is_active' => false, 'team_id' => null])->save();

            // The sync gives every login the same default password; lock it.
            $employee->user?->forceFill(['password' => Hash::make(Str::random(40))])->save();
        });
    }
}
