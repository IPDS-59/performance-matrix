<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipRkData;
use App\Kinetik\Data\KipSkpTree;
use App\Models\PerformanceIndicator;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;

/**
 * IKU of a team from kipApp's chain:
 *
 *   PK Kepala → Sasaran (Kepala RK with iscopypk=1) → IKU (IKI of that RK)
 *   RK Ketua (rencanakinerjaatasan = Sasaran) → Proyek (proyek.rencanakinerjaketua = RK Ketua)
 *
 * Each Projek gets the IKU of its RK Ketua's Sasaran. The team keeps one IKU
 * row per IKU it supports. Old rows that held the RK Ketua instead of an IKU
 * are removed once no Projek uses them.
 */
class SyncPerformanceAgreementAction
{
    /**
     * @return int Projek linked to an IKU
     */
    public function syncTeam(KipStructureSource $source, Team $team): int
    {
        $leaderPegawaiId = $team->leader?->kip_pegawai_id;
        if (! $leaderPegawaiId) {
            return 0;
        }

        $leaderTree = $this->tree($source, (string) $leaderPegawaiId);
        if ($leaderTree === null || $leaderTree->atasanPegawaiId === null) {
            return 0;
        }

        $kepalaTree = $this->tree($source, $leaderTree->atasanPegawaiId);
        if ($kepalaTree === null) {
            return 0;
        }

        // RK Ketua text → its Sasaran text, and Sasaran text → IKU rows.
        $sasaranOf = $leaderTree->rks->mapWithKeys(fn (KipRkData $rk) => [self::norm($rk->name) => trim((string) ($rk->raw['rencanakinerjaatasan'] ?? ''))]);
        $ikuOf = $kepalaTree->performanceAgreementRks()->mapWithKeys(fn (KipRkData $rk) => [
            self::norm($rk->name) => ['sasaran' => $rk->name, 'iki' => $kepalaTree->ikiByRk->get($rk->externalId, [])],
        ]);

        $linked = 0;
        foreach (Project::where('team_id', $team->id)->whereNotNull('leader_rk')->get() as $project) {
            $sasaran = $sasaranOf->get(self::norm($project->leader_rk));
            $agreement = $sasaran ? $ikuOf->get(self::norm($sasaran)) : null;
            if ($agreement === null || $agreement['iki'] === []) {
                continue;
            }

            $indicators = collect($agreement['iki'])->map(fn (array $iki) => $this->upsertIku($team, $agreement['sasaran'], $iki));
            $project->update(['performance_indicator_id' => $indicators->first()->id]);
            $linked++;
        }

        // Rows synced before this change held the RK Ketua, not an IKU.
        PerformanceIndicator::where('team_id', $team->id)
            ->whereNotNull('kip_external_id')
            ->where('kip_external_id', 'not like', 'iki:%')
            ->whereDoesntHave('projects')
            ->delete();

        return $linked;
    }

    /**
     * @param  array<string, mixed>  $iki
     */
    private function upsertIku(Team $team, string $sasaran, array $iki): PerformanceIndicator
    {
        [$target, $unit] = KipRkData::parseTarget($iki['iki'] ?? null);

        return PerformanceIndicator::updateOrCreate(
            ['kip_external_id' => "iki:{$iki['ikiid']}:team:{$team->id}"],
            [
                'team_id' => $team->id,
                'year' => (int) config('kinetik.kip.tahun'),
                'name' => trim((string) preg_replace('/\s*:\s*[\d.,]+\s*\S*\s*$/u', '', (string) ($iki['iki'] ?? ''))) ?: 'IKU',
                'sasaran' => $sasaran,
                'target' => $target,
                'target_unit' => $unit,
            ],
        );
    }

    /**
     * The Kepala's tree is shared by every team, and the sync runs one team per
     * request, so trees are cached for the length of a sync run.
     */
    private function tree(KipStructureSource $source, string $pegawaiId): ?KipSkpTree
    {
        return Cache::remember("kinetik:skp-tree:{$pegawaiId}", now()->addMinutes(30), fn () => $source->fetchSkpTree($pegawaiId));
    }

    private static function norm(?string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
    }
}
