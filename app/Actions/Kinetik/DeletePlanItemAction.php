<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\KipActivity;
use App\Models\PlanItem;
use App\Services\Kinetik\MemberKipToken;
use RuntimeException;
use Throwable;

/**
 * Deletes the kipApp kegiatan of a plan (KIP_EDIT_AFTER_PUSH) and marks the plan cancelled. The row
 * stays in Kinetik for the Friday evaluation. If kipApp refuses, nothing changes in Kinetik, unless the
 * kegiatan is already gone from kipApp (deleted there directly): then the delete has nothing left to do.
 */
class DeletePlanItemAction
{
    public function __construct(private readonly MemberKipToken $tokens) {}

    /**
     * @throws RuntimeException with a message for the user
     */
    public function execute(PlanItem $plan): void
    {
        $user = $plan->employee?->user ?? throw new RuntimeException('Pegawai belum punya akun.');
        $writer = new ApiKipWriter($this->tokens->for($user));

        try {
            $writer->deleteActivity((string) $plan->kip_external_id);
        } catch (KipApiException $e) {
            if (! $this->alreadyGone($writer, $plan, $e)) {
                throw new RuntimeException('kipApp menolak penghapusan: '.$e->getMessage());
            }
        }

        $plan->update(['status' => 'cancelled']);
        KipActivity::where('employee_id', $plan->employee_id)->where('external_id', (string) $plan->kip_external_id)->delete();
    }

    /**
     * Both kipApp's own answer ("tidak ditemukan") and a search of every quarter SKP must say the kegiatan is gone, so a
     * lookup that fails quietly never cancels a plan whose kegiatan is still there.
     */
    private function alreadyGone(ApiKipWriter $writer, PlanItem $plan, KipApiException $refusal): bool
    {
        if (stripos($refusal->getMessage(), 'tidak ditemukan') === false || blank($plan->employee?->kip_pegawai_id)) {
            return false;
        }

        try {
            return app(LocatePlanItemActivityAction::class)->execute($writer, $plan) === null;
        } catch (Throwable) {
            return false;
        }
    }
}
