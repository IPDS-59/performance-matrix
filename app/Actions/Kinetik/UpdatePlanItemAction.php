<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\PlanStatus;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Services\Kinetik\MemberKipToken;
use RuntimeException;

/**
 * Edits a kegiatan that is already in kipApp (KIP_EDIT_AFTER_PUSH): kipApp gets the full row first
 * (PUT /kegiatan) and Kinetik saves only after kipApp accepted it. If kipApp refuses, its message
 * goes to the member and the Kinetik row stays as it was.
 */
class UpdatePlanItemAction
{
    public function __construct(private readonly MemberKipToken $tokens) {}

    /**
     * @param  array<string, mixed>  $attributes  the new model attributes
     *
     * @throws RuntimeException with a message for the user
     */
    public function execute(PlanItem $plan, array $attributes): void
    {
        $user = $plan->employee?->user ?? throw new RuntimeException('Pegawai belum punya akun.');
        $next = (clone $plan)->fill($attributes);

        try {
            $writer = new ApiKipWriter($this->tokens->for($user));
            $found = app(LocatePlanItemActivityAction::class)->execute($writer, $plan);
            $row = $found['row'] ?? null;
            if ($row === null) {
                throw new RuntimeException('Kegiatan ini sudah tidak ada di kipApp. Hubungi PJ atau buat rencana baru.');
            }

            // kipApp's own edit form lets the RK change while the kegiatan is unsent: PUT v1/kegiatan with the new rkid
            // (verified live 2026-11-08, the row came back under the new RK). The RK must be one of the member's RK in that SKP.
            $rk = $row;
            if ((int) $next->performance_plan_id !== (int) $plan->performance_plan_id) {
                $rk = $this->rkIn($writer->rks($found['skp_id']), PerformancePlan::find($next->performance_plan_id))
                    ?? throw new RuntimeException('RK itu belum ada di SKP triwulan pegawai di kipApp, jadi kegiatan tidak bisa dipindahkan ke sana.');
                $next->kip_rk_id = (string) $rk['rkid'];
            }

            $fields = $next->kipFields();
            $writer->updateActivity((string) $plan->kip_external_id, [
                'skpid' => $found['skp_id'],
                'rkid' => (string) $rk['rkid'],
                'rencanakinerja' => (string) $rk['rencanakinerja'],
                'kegiatan' => $next->description,
                ...$fields,
                'capaian' => $fields['capaian'] !== '' ? $fields['capaian'] : (string) ($row['capaian'] ?? ''),
                'datadukung' => $fields['datadukung'] !== '' ? $fields['datadukung'] : (string) ($row['datadukung'] ?? ''),
            ]);
        } catch (KipApiException $e) {
            throw new RuntimeException('kipApp menolak perubahan: '.$e->getMessage());
        }

        $next->status = PlanStatus::fromKipProgress((int) $next->progress)->value;
        $next->kip_synced_at = now();
        $next->save();
    }

    /**
     * The member's RK row in the SKP that stands for this RK: by the kipApp id when it is the same copy, else by its text.
     *
     * @param  list<array<string, mixed>>  $rks
     * @return array<string, mixed>|null
     */
    private function rkIn(array $rks, ?PerformancePlan $plan): ?array
    {
        if ($plan === null) {
            return null;
        }
        $norm = fn (?string $t) => mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $t)));

        return collect($rks)->first(fn (array $r) => $plan->kip_external_id !== null && (string) $r['rkid'] === (string) $plan->kip_external_id)
            ?? collect($rks)->first(fn (array $r) => $norm($r['rencanakinerja'] ?? null) === $norm($plan->description));
    }
}
