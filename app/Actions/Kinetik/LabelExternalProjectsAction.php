<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipProjectData;
use App\Kinetik\Data\KipRkData;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Team;
use Illuminate\Support\Collection;

/**
 * Names the kipApp team and projek of the member's RK from teams Kinetik does not hold (a Pusat team), on the plan row the
 * member owns for it. They stay labels: the Pusat team and projek get no rows, so no team list, recap or dashboard shows them.
 * The projek is the one the member is anggota of under the RK's RK Ketua; when that leaves several, kipApp's RK Anggota per
 * projek (rk/proyek) decides. A projek that still cannot be told apart is left blank, with the team name alone.
 */
class LabelExternalProjectsAction
{
    /** @var array<string, Collection<int, KipProjectData>> timkerjaid => its projek, read once per run */
    private array $projects = [];

    public function __construct(private readonly KipStructureSource $source) {}

    /** @param  Collection<int, KipRkData>  $rks */
    public function execute(Employee $employee, Collection $rks): void
    {
        $held = Team::whereNotNull('kip_external_id')->pluck('kip_external_id')->map(fn ($id) => (string) $id)->flip();
        $owned = PerformancePlan::where('owner_employee_id', $employee->id)->whereNull('team_id')->where('is_additional', false)->get();

        foreach ($rks as $rk) {
            if ($rk->teamExternalId === null || $held->has($rk->teamExternalId)) {
                continue;
            }
            $plan = $owned->first(fn (PerformancePlan $p) => self::norm($p->description) === self::norm($rk->name));
            if ($plan === null) {
                continue;
            }
            $project = $this->projectOf($rk, (string) $employee->nip_lama);
            $plan->forceFill([
                'external_team_name' => trim((string) ($rk->raw['namatim'] ?? '')) ?: $project?->teamName,
                'external_project_name' => $project?->name,
            ])->save();
        }
    }

    private function projectOf(KipRkData $rk, string $nipLama): ?KipProjectData
    {
        $team = $rk->teamExternalId;
        $this->projects[$team] ??= $this->source->fetchTeamProjects($team);
        $leaderRk = self::norm($rk->raw['rencanakinerjaatasan'] ?? '');

        $candidates = $this->projects[$team]->filter(fn (KipProjectData $p) => $p->members->contains(fn ($m) => (string) $m->nipLama === $nipLama)
            && ($leaderRk === '' || self::norm($p->ikuName) === $leaderRk))->values();

        if ($candidates->count() > 1) {
            $candidates = $candidates->filter(fn (KipProjectData $p) => collect($this->source->fetchProjectRkRows($team, $p->externalId))
                ->contains(fn (array $row) => $row['niplama'] === $nipLama && self::norm($row['text']) === self::norm($rk->name)))->values();
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private static function norm(?string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
    }
}
