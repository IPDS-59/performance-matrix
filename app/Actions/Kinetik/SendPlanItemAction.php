<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PlanItem;
use App\Services\Kinetik\MemberKipToken;
use App\Services\Kinetik\PlanItemAccess;
use RuntimeException;

/**
 * "Kirim ke atasan" and "Batalkan kirim" for the kegiatan of plan items, with each member's own
 * token. The kegiatan must already be in kipApp. A list of plans of one member goes out as one
 * POST /kegiatan/kirim (or /batal) call, like kipApp's bulk menu. Every plan is checked first, so
 * one bad plan sends nothing.
 */
class SendPlanItemAction
{
    public function __construct(private readonly MemberKipToken $tokens, private readonly PlanItemAccess $access) {}

    /**
     * @param  PlanItem|iterable<PlanItem>  $plans
     *
     * @throws RuntimeException with a message for the user
     */
    public function send(PlanItem|iterable $plans): void
    {
        $this->run($plans, false);
    }

    /**
     * @param  PlanItem|iterable<PlanItem>  $plans
     *
     * @throws RuntimeException with a message for the user
     */
    public function cancel(PlanItem|iterable $plans): void
    {
        $this->run($plans, true);
    }

    /** @param  PlanItem|iterable<PlanItem>  $plans */
    private function run(PlanItem|iterable $plans, bool $cancel): void
    {
        $plans = collect($plans instanceof PlanItem ? [$plans] : $plans)->values();
        if ($plans->isEmpty()) {
            throw new RuntimeException('Tidak ada kegiatan yang dipilih');
        }

        foreach ($plans as $plan) {
            $this->guard($plan);
            if (! $cancel && $plan->kip_sent_at !== null) {
                throw new RuntimeException('Kegiatan ini sudah dikirim ke atasan.');
            }
            if ($cancel && $plan->kip_sent_at === null) {
                throw new RuntimeException('Kegiatan ini belum dikirim ke atasan.');
            }
            if (! $this->access->skpAllowsSend($plan)) {
                throw new RuntimeException(PlanItemAccess::LOCK_REASON);
            }
        }

        foreach ($plans->groupBy('employee_id') as $group) {
            $ids = $group->map(fn (PlanItem $p) => (string) $p->kip_external_id)->all();
            try {
                $writer = $this->writer($group->first());
                $cancel ? $writer->cancelSend($ids) : $writer->sendActivities($ids);
            } catch (KipApiException $e) {
                throw new RuntimeException(($cancel ? 'kipApp menolak pembatalan: ' : 'kipApp menolak pengiriman: ').$e->getMessage());
            }
            PlanItem::whereKey($group->pluck('id'))->update(['kip_sent_at' => $cancel ? null : now()]);
        }
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
