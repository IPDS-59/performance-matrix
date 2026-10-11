<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Credit\FunctionalLevel;
use App\Kinetik\Data\KipPositionData;
use App\Models\Employee;
use App\Models\EmployeeCareer;
use App\Models\KipPerformanceRating;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pull golongan, jabatan and every periodic SKP rating from kipApp for the
 * Angka Kredit pages. Updates in place; the admin's PAK value is kept.
 */
class SyncKipCareersAction
{
    public function __construct(private readonly KipStructureSource $source) {}

    /**
     * @return array{employees: int, ratings: int, failed: int}
     */
    public function execute(): array
    {
        $result = ['employees' => 0, 'ratings' => 0, 'failed' => 0];

        Employee::query()
            ->where('is_active', true)
            ->whereNotNull('nip_lama')
            ->orderBy('id')
            ->each(function (Employee $employee) use (&$result) {
                try {
                    $result['ratings'] += $this->syncEmployee($employee);
                    $result['employees']++;
                } catch (Throwable $e) {
                    $result['failed']++;
                    Log::warning('Career sync failed', ['employee_id' => $employee->id, 'error' => $e->getMessage()]);
                }
            });

        return $result;
    }

    /**
     * @return int ratings stored
     */
    public function syncEmployee(Employee $employee): int
    {
        $history = $this->source->fetchPositionHistory((string) $employee->nip_lama);
        if ($history === []) {
            return 0;
        }

        $this->saveCareer($employee, $history);

        $stored = 0;
        foreach ($history as $position) {
            foreach ($this->source->fetchPeriodicRatings($position->pegawaiId) as $rating) {
                KipPerformanceRating::updateOrCreate(['kip_skp_id' => $rating->skpId], [
                    'employee_id' => $employee->id,
                    'period_start' => $rating->periodStart,
                    'period_end' => $rating->periodEnd,
                    'jabatan' => $rating->jabatan,
                    'predikat' => $rating->predikat,
                    'nilai_prestasi' => $rating->nilaiPrestasi,
                    'status' => $rating->status,
                ]);
                $stored++;
            }
        }

        return $stored;
    }

    /**
     * @param  list<KipPositionData>  $history  oldest first
     */
    private function saveCareer(Employee $employee, array $history): void
    {
        $current = end($history);
        $level = FunctionalLevel::fromJabatan($current->jabatan);

        // The unbroken run of latest records with the current golongan / jenjang.
        $since = function (callable $same) use ($history): ?KipPositionData {
            $first = null;
            foreach (array_reverse($history) as $position) {
                if (! $same($position)) {
                    break;
                }
                $first = $position;
            }

            return $first;
        };

        $golonganStart = $since(fn (KipPositionData $p) => $p->golongan === $current->golongan);
        $levelStart = $level ? $since(fn (KipPositionData $p) => FunctionalLevel::fromJabatan($p->jabatan) === $level) : null;

        EmployeeCareer::updateOrCreate(['employee_id' => $employee->id], [
            'jabatan' => $current->jabatan,
            'golongan' => $current->golongan,
            'pangkat' => $current->pangkat,
            'golongan_since' => $golonganStart?->tmt,
            'level_since' => $levelStart?->tmt,
            'level_start_golongan' => $levelStart?->golongan,
            'synced_at' => now(),
        ]);
    }
}
