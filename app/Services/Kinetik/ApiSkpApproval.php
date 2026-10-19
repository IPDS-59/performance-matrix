<?php

namespace App\Services\Kinetik;

use App\Kinetik\Contracts\SkpApproval;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\SkpStatus;
use App\Models\User;
use RuntimeException;

/** SkpApproval against the live kipApp, with the atasan's own token. */
class ApiSkpApproval implements SkpApproval
{
    private const SET_FLAG = 6;

    public function __construct(private readonly MemberKipToken $tokens) {}

    public function pegawaiId(User $user): ?string
    {
        $employee = $user->employee;
        if ($employee === null) {
            return null;
        }
        if ($employee->kip_pegawai_id !== null) {
            return (string) $employee->kip_pegawai_id;
        }

        $id = (new ApiKipWriter($this->tokens->for($user)))->me()['id'] ?? null;
        if (filled($id)) {
            $employee->forceFill(['kip_pegawai_id' => (string) $id])->save();
        }

        return filled($id) ? (string) $id : null;
    }

    public function approve(User $atasan, SkpStatus $skp): void
    {
        if ($skp->kind !== 'yearly') {
            throw new RuntimeException('Hanya SKP Tahunan yang ditetapkan dari sini. SKP triwulan dinilai di kipApp.');
        }
        $writer = $this->checked($atasan, $skp);
        $writer->submitSkp($skp->kip_skp_id, self::SET_FLAG);
        $this->refresh($writer, $skp);
    }

    public function sendBack(User $atasan, SkpStatus $skp): void
    {
        $writer = $this->checked($atasan, $skp);
        $writer->returnSkp($skp->kip_skp_id);
        $this->refresh($writer, $skp);
    }

    /** The atasan must be this SKP's atasan and the SKP must still wait, as kipApp has it right now. */
    public function checked(User $atasan, SkpStatus $skp): ApiKipWriter
    {
        $mine = $this->pegawaiId($atasan);
        if ($mine === null || $skp->atasan_pegawai_id !== $mine) {
            throw new RuntimeException('Anda bukan atasan SKP ini.');
        }

        $writer = new ApiKipWriter($this->tokens->for($atasan));
        $now = $writer->skpById($skp->kip_skp_id);
        if ($now === null) {
            throw new RuntimeException('SKP tidak ditemukan di kipApp.');
        }
        SkpStatus::remember($skp->employee_id, $skp->tahun, $skp->kind, $skp->quarter, $now);
        if (! $skp->fresh()->isWaiting()) {
            throw new RuntimeException('SKP ini sudah tidak menunggu Anda. Statusnya sekarang "'.($now['statusskp'] ?? '?').'".');
        }

        return $writer;
    }

    public function refresh(ApiKipWriter $writer, SkpStatus $skp): void
    {
        if (($now = $writer->skpById($skp->kip_skp_id)) !== null) {
            SkpStatus::remember($skp->employee_id, $skp->tahun, $skp->kind, $skp->quarter, $now);
        }
    }
}
