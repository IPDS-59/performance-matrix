<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PlanItem;
use App\Services\Kinetik\MemberKipToken;
use RuntimeException;

/**
 * Deletes the kipApp kegiatan of a plan (KIP_EDIT_AFTER_PUSH) and marks the plan cancelled. The row
 * stays in Kinetik for the Friday evaluation. If kipApp refuses, nothing changes in Kinetik.
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

        try {
            (new ApiKipWriter($this->tokens->for($user)))->deleteActivity((string) $plan->kip_external_id);
        } catch (KipApiException $e) {
            throw new RuntimeException('kipApp menolak penghapusan: '.$e->getMessage());
        }

        $plan->update(['status' => 'cancelled']);
    }
}
