<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PlanItem;
use App\Services\Kinetik\MemberKipToken;
use RuntimeException;

/**
 * A member marks a plan complete in Kinetik (Q9b): progres 100 with a capaian
 * note and an evidence link go to kipApp. Pushes the kegiatan first when it is
 * not in kipApp yet.
 */
class CompletePlanItemAction
{
    public function __construct(private readonly MemberKipToken $tokens, private readonly PushPlanItemAction $push) {}

    /**
     * @throws RuntimeException with a message for the user
     */
    public function execute(PlanItem $plan, ?string $note, ?string $evidenceUrl, ?float $capaian = null): void
    {
        if ($plan->kip_external_id === null && ! $this->push->execute($plan)) {
            throw new RuntimeException($plan->fresh()->push_error ?? 'Kegiatan belum bisa dikirim ke kipApp.');
        }

        $note ??= $plan->capaian_note;
        $evidenceUrl ??= $plan->evidence_url;
        $user = $plan->employee?->user ?? throw new RuntimeException('Pegawai belum punya akun.');

        try {
            $writer = new ApiKipWriter($this->tokens->for($user));
            $row = collect($writer->activities((string) $plan->kip_skp_id))
                ->first(fn (array $r) => (string) $r['kegiatanperhariid'] === (string) $plan->kip_external_id);

            if ($row === null) {
                throw new RuntimeException('Kegiatan ini sudah tidak ada di kipApp. Hubungi PJ atau buat rencana baru.');
            }

            $writer->updateActivity((string) $plan->kip_external_id, [
                'skpid' => (string) $plan->kip_skp_id,
                'rkid' => (string) $row['rkid'],
                'rencanakinerja' => (string) $row['rencanakinerja'],
                'kegiatan' => (string) $row['kegiatan'],
                'tanggal' => (string) $row['tanggal'],
                // KIP_NULL_TIMES on: pass the row's own null back. Off: fill what is missing (today).
                'tanggalselesai' => config('kinetik.kip.null_times') ? ($row['tanggalselesai'] ?? null) : (string) ($row['tanggalselesai'] ?? $row['tanggal']),
                'jammulai' => $row['jammulai'] ?? (config('kinetik.kip.null_times') ? null : '08:00'),
                'jamselesai' => $row['jamselesai'] ?? (config('kinetik.kip.null_times') ? null : '16:00'),
                'progres' => 100,
                'capaian' => $note ?? (string) ($row['capaian'] ?? ''),
                'datadukung' => $evidenceUrl ?? (string) ($row['datadukung'] ?? ''),
                'iscapaianskp' => $plan->is_capaian_skp ? 1 : 0,
            ]);
        } catch (KipApiException $e) {
            throw new RuntimeException('kipApp menolak perubahan: '.$e->getMessage());
        }

        $plan->update([
            'status' => 'done',
            'progress' => 100,
            'capaian' => $capaian ?? $plan->capaian,
            'capaian_note' => $note,
            'evidence_url' => $evidenceUrl,
            'kip_synced_at' => now(),
        ]);
    }
}
