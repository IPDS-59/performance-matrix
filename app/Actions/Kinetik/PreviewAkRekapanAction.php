<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Credit\FunctionalLevel;
use App\Models\Employee;
use App\Models\EmployeeCareer;

/**
 * Matches the rows of the office's "Rekapan SKP" workbook (INTEGRASI sd 2022 plus the AK of 2023, 2024 and 2025 per person)
 * to employees by NIP and says what the AK base would become. The workbook is the official record of those years; Kinetik's
 * own estimate for them (a predikat per month from kipApp) differs from it, so the cumulative figure replaces the estimate
 * and Kinetik counts again from the day after.
 */
class PreviewAkRekapanAction
{
    /** Notes that mean the starting AK is stated somewhere else than the columns, so a person must look. */
    private const MANUAL = '/PJL|inpassing|awal\s*=|penyesuaian/i';

    /**
     * @param  list<array<string, mixed>>  $rows  nip, name, integrasi, y2023, y2024, y2025, jumlah, note
     * @return list<array<string, mixed>>
     */
    public function execute(array $rows): array
    {
        $employees = Employee::with('career')->whereNotNull('nip_baru')->get()->keyBy(fn (Employee $e) => preg_replace('/\D/', '', (string) $e->nip_baru));

        return collect($rows)->values()->map(function (array $row, int $i) use ($employees) {
            $nip = preg_replace('/\D/', '', (string) ($row['nip'] ?? ''));
            $employee = $employees->get($nip);
            $years = collect([$row['y2023'] ?? null, $row['y2024'] ?? null, $row['y2025'] ?? null])->filter(fn ($v) => $v !== null);
            $integrasi = $row['integrasi'] ?? null;
            $note = trim((string) ($row['note'] ?? ''));
            $notes = [];

            $proposed = $row['jumlah'] ?? (($integrasi !== null || $years->isNotEmpty()) ? round((float) $integrasi + $years->sum(), 3) : null);
            if ($proposed === null) {
                $notes[] = 'Tidak ada angka AK pada baris ini.';
            }
            if ($employee === null) {
                $notes[] = $nip === '' ? 'Baris tanpa NIP.' : 'NIP tidak ada di Kinetik.';
            }
            if ($note !== '' && preg_match(self::MANUAL, $note)) {
                $notes[] = 'Keterangan menyebut AK awal di luar kolom ("'.$note.'"). Periksa dokumennya sebelum mengimpor.';
            }
            if ($integrasi === null && $row['jumlah'] === null && $years->isNotEmpty()) {
                $notes[] = 'Tanpa integrasi: angka dihitung dari AK 2023 sampai 2025 saja.';
            }
            if ($employee !== null && FunctionalLevel::fromJabatan($employee->career?->jabatan) === null) {
                $notes[] = 'Bukan jabatan fungsional di kipApp, jadi AK tidak dihitung.';
            }

            $blocked = $employee === null || $proposed === null;
            $manual = collect($notes)->contains(fn ($n) => str_contains($n, 'Periksa dokumennya'));

            return [
                'index' => $i,
                'nip' => $nip,
                'name' => $row['name'] ?? null,
                'employee_id' => $employee?->id,
                'employee_name' => $employee ? ($employee->display_name ?? $employee->name) : null,
                'integrasi' => $integrasi,
                'y2023' => $row['y2023'] ?? null,
                'y2024' => $row['y2024'] ?? null,
                'y2025' => $row['y2025'] ?? null,
                'proposed' => $proposed,
                'current_base' => $employee?->career?->ak_base,
                'current_source' => $employee?->career?->ak_base_source,
                'note' => $note ?: null,
                'notes' => $notes,
                'blocked' => $blocked,
                'include' => ! $blocked && ! $manual,
            ];
        })->all();
    }

    /**
     * Stores the cumulative AK as the person's base at $baseDate.
     *
     * @param  list<array{employee_id: int, ak: float}>  $rows
     */
    public function apply(array $rows, string $baseDate): int
    {
        $saved = 0;
        foreach ($rows as $row) {
            EmployeeCareer::updateOrCreate(['employee_id' => $row['employee_id']], [
                'ak_base' => $row['ak'],
                'ak_base_date' => $baseDate,
                'ak_base_source' => 'rekapan',
            ]);
            $saved++;
        }

        return $saved;
    }
}
