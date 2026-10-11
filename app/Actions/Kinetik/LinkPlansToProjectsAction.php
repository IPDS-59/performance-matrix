<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Data\KipRkData;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Link RK to Projek through kipApp's hierarchy: a Projek hangs under one RK
 * of the team leader, and a member RK is cascaded from one leader RK. When the
 * leader RK owns exactly one Projek, the RK belongs to that Projek. When it
 * owns several, the member picks among those (see candidates()).
 */
class LinkPlansToProjectsAction
{
    /** @var array<int, array{any: bool, byLeader: Collection<string, Collection<int, int>>}> */
    private array $teamProjects = [];

    /**
     * Set project_id on RK that have no Projek yet and whose leader RK owns
     * exactly one Projek. Never changes a Projek that is already set.
     *
     * @return int RK linked
     */
    public function execute(?int $teamId = null): int
    {
        $candidates = $this->projectsByLeaderRk($teamId);
        $linked = 0;

        PerformancePlan::query()
            ->whereNull('project_id')
            ->whereNotNull('leader_rk')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->each(function (PerformancePlan $plan) use ($candidates, &$linked) {
                $projects = $candidates->get(self::key($plan->team_id, $plan->leader_rk), collect());
                if ($projects->count() === 1) {
                    $plan->update(['project_id' => $projects->first()]);
                    $linked++;
                }
            });

        return $linked;
    }

    /**
     * Store the leader RK of each kipApp RK on the matching local RK: by the
     * kipApp RK id, else by team and RK text (the RK sync keeps one local RK
     * per text).
     *
     * @param  Collection<int, KipRkData>  $rks
     * @return int local RK updated
     */
    public function rememberLeaderRks(Collection $rks): int
    {
        $teamIds = Team::whereIn('kip_external_id', $rks->pluck('teamExternalId')->filter()->unique())->pluck('id', 'kip_external_id');
        $updated = 0;

        foreach ($rks as $rk) {
            $leader = trim((string) ($rk->raw['rencanakinerjaatasan'] ?? ''));
            if ($leader === '' || $rk->name === '') {
                continue;
            }

            $teamId = $teamIds->get($rk->teamExternalId);
            $updated += PerformancePlan::query()
                ->where(fn ($q) => $q->where('kip_external_id', $rk->externalId)
                    ->when($teamId, fn ($q) => $q->orWhere(fn ($q) => $q->where('team_id', $teamId)->where('description', $rk->name))))
                ->where(fn ($q) => $q->whereNull('leader_rk')->orWhere('leader_rk', '!=', $leader))
                ->update(['leader_rk' => $leader]);
        }

        return $updated;
    }

    /**
     * Projek ids under each team RK's leader RK, keyed by RK id. Only RK whose
     * leader RK owns two or more Projek are listed.
     *
     * @param  Collection<int, PerformancePlan>  $plans
     * @return Collection<int, list<int>>
     */
    public function candidates(Collection $plans): Collection
    {
        $teamIds = $plans->pluck('team_id')->filter()->unique();
        if ($teamIds->isEmpty()) {
            return collect();
        }

        $byLeader = $this->projectsByLeaderRk(null, $teamIds->all());

        return $plans
            ->filter(fn (PerformancePlan $plan) => $plan->project_id === null && filled($plan->leader_rk))
            ->mapWithKeys(fn (PerformancePlan $plan) => [
                $plan->id => $byLeader->get(self::key($plan->team_id, $plan->leader_rk), collect())->values()->all(),
            ])
            ->filter(fn (array $ids) => count($ids) > 1);
    }

    /**
     * May a claim on this RK have no Projek? Only when the RK is not tied to a
     * Projek, and either its team has no Projek at all or kipApp shows its
     * leader RK owns none (e.g. a Zona Integritas RK). Otherwise the member
     * must pick one.
     */
    public function projectOptional(PerformancePlan $plan): bool
    {
        if ($plan->project_id !== null || $plan->team_id === null) {
            return $plan->project_id === null;
        }
        // Cached per team: the claim form asks this for every RK of the member's teams.
        $this->teamProjects[$plan->team_id] ??= [
            'any' => Project::where('team_id', $plan->team_id)->exists(),
            'byLeader' => $this->projectsByLeaderRk($plan->team_id),
        ];
        $team = $this->teamProjects[$plan->team_id];

        if (! $team['any']) {
            return true;
        }
        if (blank($plan->leader_rk)) {
            return false;
        }

        return $team['byLeader']->get(self::key($plan->team_id, $plan->leader_rk), collect())->isEmpty();
    }

    /**
     * @param  list<int>|null  $teamIds
     * @return Collection<string, Collection<int, int>>
     */
    private function projectsByLeaderRk(?int $teamId, ?array $teamIds = null): Collection
    {
        return Project::query()
            ->whereNotNull('leader_rk')
            ->when($teamId, fn ($q) => $q->where('team_id', $teamId))
            ->when($teamIds, fn ($q) => $q->whereIn('team_id', $teamIds))
            ->get(['id', 'team_id', 'leader_rk'])
            ->groupBy(fn (Project $p) => self::key($p->team_id, $p->leader_rk))
            ->map(fn (Collection $projects) => $projects->pluck('id'));
    }

    private static function key(?int $teamId, ?string $leaderRk): string
    {
        return $teamId.'|'.mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $leaderRk)));
    }
}
