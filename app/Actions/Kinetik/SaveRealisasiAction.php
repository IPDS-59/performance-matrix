<?php

namespace App\Actions\Kinetik;

use App\Models\PlanItem;
use App\Services\Kinetik\PlanItemAccess;
use RuntimeException;

/**
 * Saves the realisasi of a kegiatan (capaian, progres, uraian, bukti dukung, permasalahan) and keeps kipApp in step.
 *
 * Kinetik is written first, so nothing typed is lost. A kegiatan that is already in kipApp and not yet sent to the atasan is
 * then updated in kipApp too: before this, "Simpan" kept the bukti dukung in Kinetik only and kipApp stayed empty
 * (found on production 2026-11-10: 35 kegiatan with a bukti dukung in Kinetik and none in kipApp).
 */
class SaveRealisasiAction
{
    /** Not in kipApp yet: it goes there once complete (PushDuePlansAction). */
    public const LOCAL = 'local';

    /** Kinetik and kipApp both hold the new values. */
    public const SYNCED = 'synced';

    /** Sent to the atasan or the SKP is locked: kipApp cannot change, Kinetik holds the values. */
    public const LOCKED = 'locked';

    /** kipApp refused or could not be reached: Kinetik holds the values, kipApp is behind. */
    public const FAILED = 'failed';

    /** The fields kipApp also stores. */
    private const KIP_FIELDS = ['progress', 'capaian_note', 'evidence_url', 'is_capaian_skp'];

    public function __construct(private readonly UpdatePlanItemAction $kip, private readonly PlanItemAccess $access) {}

    /**
     * @param  array<string, mixed>  $values  model attributes to save
     * @param  bool  $always  false: write to kipApp only when a field kipApp stores changed (bulk saves)
     * @return array{outcome: string, reason: ?string}
     */
    public function execute(PlanItem $plan, array $values, bool $always = true): array
    {
        $before = $plan->only(self::KIP_FIELDS);
        $plan->update($values);

        if ($plan->kip_external_id === null) {
            return ['outcome' => self::LOCAL, 'reason' => null];
        }
        if ($plan->kip_sent_at !== null) {
            return ['outcome' => self::LOCKED, 'reason' => 'Kegiatan sudah dikirim ke atasan. Batalkan kirim dulu bila kipApp perlu diubah.'];
        }
        if ($reason = $this->access->lockReason($plan)) {
            return ['outcome' => self::LOCKED, 'reason' => $reason];
        }
        if (! $always && $plan->only(self::KIP_FIELDS) == $before) {
            return ['outcome' => self::LOCAL, 'reason' => null];
        }

        try {
            // Sends the whole row as Kinetik now has it; kipApp keeps its own uraian and bukti where Kinetik has none.
            $this->kip->execute($plan->loadMissing(['employee.user', 'performancePlan']), []);
        } catch (RuntimeException $e) {
            return ['outcome' => self::FAILED, 'reason' => $e->getMessage()];
        } catch (\Throwable $e) {
            report($e);

            return ['outcome' => self::FAILED, 'reason' => 'kipApp tidak dapat dihubungi.'];
        }

        return ['outcome' => self::SYNCED, 'reason' => null];
    }
}
