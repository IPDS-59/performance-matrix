<?php

namespace App\Services\Kinetik;

use App\Kinetik\Contracts\SkpApproval;
use App\Models\SkpStatus;
use App\Models\User;
use RuntimeException;

/** SkpApproval for KIP_SOURCE=mock: only the local rows change, so the page can be tried without kipApp. */
class MockSkpApproval implements SkpApproval
{
    public function pegawaiId(User $user): ?string
    {
        return $user->employee?->kip_pegawai_id !== null ? (string) $user->employee->kip_pegawai_id : null;
    }

    public function approve(User $atasan, SkpStatus $skp): void
    {
        if ($skp->kind !== 'yearly' || ! $skp->isWaiting()) {
            throw new RuntimeException('SKP ini tidak bisa ditetapkan dari sini.');
        }
        $skp->update(['status_id' => 6, 'status_text' => 'Ditetapkan', 'status_changed_at' => now()]);
    }

    public function sendBack(User $atasan, SkpStatus $skp): void
    {
        $skp->update(['status_id' => 1, 'status_text' => 'Sedang dibuat', 'status_changed_at' => now()]);
    }
}
