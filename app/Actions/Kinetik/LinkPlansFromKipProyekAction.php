<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Cache;

/**
 * Fills the projek of RK that have none from kipApp's own list of RK Anggota per projek (rk/proyek), the exact answer
 * the leader-RK guess in LinkPlansToProjectsAction can only approximate. The structure sync runs per team, so each
 * team adds its projek to a shared map in the cache and every run then resolves against everything read so far.
 * An RK text that hangs under exactly one projek links to it; a text under several is left for the member to choose.
 * Never changes a projek that is already set.
 */
class LinkPlansFromKipProyekAction
{
    private const CACHE_KEY = 'kinetik.rk_proyek_map';

    /** @return int RK linked */
    public function execute(KipStructureSource $source, Team $team): int
    {
        $map = Cache::get(self::CACHE_KEY, []);

        foreach (Project::where('team_id', $team->id)->whereNotNull('kip_external_id')->get(['id', 'kip_external_id']) as $project) {
            try {
                $texts = $source->fetchProjectRkTexts((string) $team->kip_external_id, (string) $project->kip_external_id);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            foreach ($map as $text => $ids) {
                $map[$text] = array_values(array_diff($ids, [$project->id]));
            }
            foreach ($texts as $text) {
                $map[self::norm($text)][] = $project->id;
            }
        }
        Cache::put(self::CACHE_KEY, $map = array_filter(array_map(fn ($ids) => array_values(array_unique($ids)), $map)), now()->addDay());

        return $this->resolve($map);
    }

    /** @param  array<string, list<int>>  $map */
    public function resolve(array $map): int
    {
        $linked = 0;
        PerformancePlan::whereNull('project_id')->each(function (PerformancePlan $plan) use ($map, &$linked) {
            $ids = $map[self::norm($plan->description)] ?? [];
            if (count($ids) === 1) {
                $plan->update(['project_id' => $ids[0]]);
                $linked++;
            }
        });

        return $linked;
    }

    private static function norm(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($text)));
    }
}
