<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Sources\ApiKipWriter;
use App\Models\Employee;
use App\Services\Kinetik\MemberKipToken;
use Carbon\Carbon;
use RuntimeException;

/**
 * The member's presensi activities for the quarter of a date ("List Aktivitas" in kipApp), read with
 * the member's own token. Read only: nothing is written to kipApp.
 */
class ListPresensiAction
{
    public function __construct(private readonly MemberKipToken $tokens) {}

    /**
     * @return list<array{id: string, date: string, description: string}>
     *
     * @throws RuntimeException with a message for the user (kipApp errors included)
     */
    public function execute(Employee $employee, Carbon $date): array
    {
        $user = $employee->user ?? throw new RuntimeException('Pegawai belum punya akun.');
        if (blank($employee->nip_lama)) {
            throw new RuntimeException('NIP lama pegawai belum terisi, jadi presensi tidak dapat diambil.');
        }

        return (new ApiKipWriter($this->tokens->for($user)))
            ->presensi((int) config('kinetik.kip.periode_id'), $date->quarter, (string) $employee->nip_lama);
    }
}
