<?php

namespace App\Console\Commands;

use App\Actions\Kinetik\SaveRealisasiAction;
use App\Models\KipActivity;
use App\Models\PlanItem;
use Illuminate\Console\Command;

/**
 * Finds kegiatan that are in kipApp, not sent to the atasan, and whose realisasi Kinetik holds but kipApp does not (uraian,
 * bukti dukung, or a higher progres). Before 2026-11-11 "Simpan realisasi" kept those in Kinetik only. Lists them; with
 * --apply it writes them to kipApp with each owner's own login. The comparison uses the last synced copy of kipApp, so
 * sync the kegiatan first.
 */
class PushRealisasiCommand extends Command
{
    protected $signature = 'kinetik:push-realisasi {--apply : write to kipApp (without it, only list)} {--employee= : one employee id}';

    protected $description = 'List (or send) realisasi that is in Kinetik but not in kipApp';

    public function handle(SaveRealisasiAction $save): int
    {
        $behind = PlanItem::with(['employee.user', 'performancePlan'])
            ->whereNotNull('kip_external_id')->whereNull('kip_sent_at')->where('status', '!=', 'cancelled')
            ->when($this->option('employee'), fn ($q, $id) => $q->where('employee_id', (int) $id))
            ->get()
            ->filter(function (PlanItem $p) {
                $kip = KipActivity::where('external_id', $p->kip_external_id)->first();
                if ($kip === null) {
                    return false;
                }

                return (filled($p->evidence_url) && blank($kip->evidence_url))
                    || (filled($p->capaian_note) && blank($kip->achievement_note))
                    || (int) $p->progress > (int) $kip->progress;
            });

        $this->info($behind->count().' kegiatan: Kinetik ahead of kipApp, across '.$behind->pluck('employee_id')->unique()->count().' people.');
        $behind->groupBy('employee_id')->each(fn ($rows) => $this->line('  '.($rows->first()->employee?->name ?? '?').': '.$rows->count()));

        if (! $this->option('apply')) {
            $this->comment('Nothing was written. Run again with --apply to send them to kipApp.');

            return self::SUCCESS;
        }

        $counts = [];
        foreach ($behind as $plan) {
            $result = $save->execute($plan, []);
            $counts[$result['outcome']] = ($counts[$result['outcome']] ?? 0) + 1;
            if ($result['outcome'] !== SaveRealisasiAction::SYNCED) {
                $this->warn("  #{$plan->id} {$result['outcome']}: {$result['reason']}");
            }
        }
        $this->info('Result: '.json_encode($counts));

        return self::SUCCESS;
    }
}
