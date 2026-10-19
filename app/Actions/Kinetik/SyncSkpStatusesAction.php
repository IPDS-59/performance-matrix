<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Sources\ApiKipWriter;
use App\Models\Employee;
use App\Models\KipCredential;
use App\Models\SkpStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reads each active employee's yearly and quarter SKP with the admin token and stores where they stand,
 * so an atasan can see what waits for them. A request that times out skips that employee and goes on.
 */
class SyncSkpStatusesAction
{
    /** @return array{employees: int, rows: int, failed: int} */
    public function execute(): array
    {
        $token = KipCredential::current()?->token ?: config('kinetik.kip.token');
        if (empty($token)) {
            throw new RuntimeException('Belum ada token kipApp admin.');
        }

        $writer = new ApiKipWriter($token);
        $periodeId = (int) config('kinetik.kip.periode_id');
        $tahun = (int) config('kinetik.kip.tahun');
        $result = ['employees' => 0, 'rows' => 0, 'failed' => 0];

        Employee::query()->where('is_active', true)->whereNotNull('kip_pegawai_id')->each(function (Employee $employee) use ($writer, $periodeId, $tahun, &$result) {
            try {
                $yearly = $writer->yearlySkp((string) $employee->kip_pegawai_id, $periodeId);
                $quarters = $writer->quarterSkps((string) $employee->kip_pegawai_id, $periodeId);
            } catch (ConnectionException $e) {
                Log::warning("kinetik:sync-skp-statuses skipped {$employee->id}: {$e->getMessage()}");
                $result['failed']++;

                return;
            }

            $result['employees']++;
            if ($yearly !== null) {
                SkpStatus::remember($employee->id, $tahun, 'yearly', 0, $yearly);
                $result['rows']++;
            }
            foreach ($quarters as $quarter => $skp) {
                SkpStatus::remember($employee->id, $tahun, 'quarter', (int) $quarter, $skp);
                $result['rows']++;
            }
        });

        return $result;
    }
}
