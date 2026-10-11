<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PlanItem;
use App\Services\Kinetik\MemberKipToken;
use RuntimeException;

/**
 * "Kirim ke atasan" and "Batalkan kirim" for the kegiatan of a plan item, with
 * the member's own token. The kegiatan must already be in kipApp.
 */
class SendPlanItemAction
{
    public function __construct(private readonly MemberKipToken $tokens) {}

    /**
     * @throws RuntimeException with a message for the user
     */
    public function send(PlanItem $plan): void
    {
        $this->guard($plan);
        if ($plan->kip_sent_at !== null) {
            throw new RuntimeException('Kegiatan ini sudah dikirim ke atasan.');
        }

        try {
            $this->writer($plan)->sendActivities([(string) $plan->kip_external_id]);
        } catch (KipApiException $e) {
            throw new RuntimeException('kipApp menolak pengiriman: '.$e->getMessage());
        }
        $plan->update(['kip_sent_at' => now()]);
    }

    /**
     * @throws RuntimeException with a message for the user
     */
    public function cancel(PlanItem $plan): void
    {
        $this->guard($plan);
        if ($plan->kip_sent_at === null) {
            throw new RuntimeException('Kegiatan ini belum dikirim ke atasan.');
        }

        try {
            $this->writer($plan)->cancelSend([(string) $plan->kip_external_id]);
        } catch (KipApiException $e) {
            throw new RuntimeException('kipApp menolak pembatalan: '.$e->getMessage());
        }
        $plan->update(['kip_sent_at' => null]);
    }

    private function guard(PlanItem $plan): void
    {
        if ($plan->kip_external_id === null || $plan->status === 'planned' || $plan->status === 'cancelled') {
            throw new RuntimeException('Kegiatan ini belum ada di kipApp. Kirim ke kipApp dulu.');
        }
    }

    private function writer(PlanItem $plan): ApiKipWriter
    {
        $user = $plan->employee?->user ?? throw new RuntimeException('Pegawai belum punya akun.');

        return new ApiKipWriter($this->tokens->for($user));
    }
}
