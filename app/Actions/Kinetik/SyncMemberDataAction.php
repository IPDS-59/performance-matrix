<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Sources\ApiKipWriter;
use App\Models\MemberSyncWait;
use App\Models\SkpStatus;
use App\Models\User;
use App\Services\Kinetik\MemberKipToken;

/**
 * One member's own sync with the member's own kipApp token, renewed with the stored password when it
 * expired: kegiatan and RK, then where the member's SKP stand. It throws MemberLoginRequiredException
 * when the member must sign in again and SsoUnavailableException when BPS SSO is down.
 */
class SyncMemberDataAction
{
    public function __construct(
        private readonly SyncMemberActivitiesAction $activities,
        private readonly MemberKipToken $tokens,
    ) {}

    /** @return array{kegiatan: int, skp: int} */
    public function execute(User $user): array
    {
        $employee = $user->employee;
        $kegiatan = $this->activities->execute($user, allowAdminFallback: false);

        $skp = 0;
        if ($employee !== null && $employee->kip_pegawai_id !== null && config('kinetik.kip.source') !== 'mock') {
            $writer = new ApiKipWriter($this->tokens->for($user));
            $periodeId = (int) config('kinetik.kip.periode_id');
            $tahun = (int) config('kinetik.kip.tahun');
            $pegawaiId = (string) $employee->kip_pegawai_id;

            if ($yearly = $writer->yearlySkp($pegawaiId, $periodeId)) {
                SkpStatus::remember($employee->id, $tahun, 'yearly', 0, $yearly);
                $skp++;
            }
            foreach ($writer->quarterSkps($pegawaiId, $periodeId) as $quarter => $row) {
                SkpStatus::remember($employee->id, $tahun, 'quarter', (int) $quarter, $row);
                $skp++;
            }
        }

        if ($employee !== null) {
            MemberSyncWait::where('employee_id', $employee->id)->delete();
        }

        return ['kegiatan' => $kegiatan, 'skp' => $skp];
    }
}
