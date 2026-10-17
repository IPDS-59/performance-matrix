<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipWriter;
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
            $row = collect($writer->activities((string) $plan->kip_skp_id))
                ->first(fn (array $r) => (string) $r['kegiatanperhariid'] === (string) $plan->kip_external_id);
            if ($row === null) {
                throw new RuntimeException('Kegiatan ini sudah tidak ada di kipApp. Hubungi PJ atau buat rencana baru.');
            }

            $fields = $next->kipFields();
            $writer->updateActivity((string) $plan->kip_external_id, [
                'skpid' => (string) $plan->kip_skp_id,
                'rkid' => (string) $row['rkid'],
                'rencanakinerja' => (string) $row['rencanakinerja'],
                'kegiatan' => $next->description,
                ...$fields,
                'capaian' => $fields['capaian'] !== '' ? $fields['capaian'] : (string) ($row['capaian'] ?? ''),
                'datadukung' => $fields['datadukung'] !== '' ? $fields['datadukung'] : (string) ($row['datadukung'] ?? ''),
            ]);
        } catch (KipApiException $e) {
            throw new RuntimeException('kipApp menolak perubahan: '.$e->getMessage());
        }

        $next->status = $next->progress >= 100 ? 'done' : ($next->progress > 0 ? 'in_progress' : 'pushed');
        $next->kip_synced_at = now();
        $next->save();
    }
}
