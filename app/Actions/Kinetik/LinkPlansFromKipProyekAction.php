<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Contracts\KipStructureSource;
use App\Models\Employee;
use App\Models\EmployeeRk;
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
                $rows = $source->fetchProjectRkRows((string) $team->kip_external_id, (string) $project->kip_external_id);
            } catch (\Throwable $e) {
                report($e);

                continue;
            }
            foreach ($map as $text => $ids) {
                $map[$text] = array_values(array_diff($ids, [$project->id]));
            }
            foreach ($rows as $row) {
                $map[self::norm($row['text'])][] = $project->id;
            }
            $this->tieMemberRks($rows, $project);
        }
        Cache::put(self::CACHE_KEY, $map = array_filter(array_map(fn ($ids) => array_values(array_unique($ids)), $map)), now()->addDay());

        return $this->resolve($map);
    }

    /**
     * skp/rk/copy and rk/proyek hand out the same ID per member, so the RK a member holds in a projek is exact: it is the
     * member's own RK with that text (a member rarely holds two RK with identical text). Stores the projek on EmployeeRk.
     *
     * @param  list<array{text: string, niplama: string}>  $rows
     */
    private function tieMemberRks(array $rows, Project $project): void
    {
        $byNip = Employee::whereIn('nip_lama', array_unique(array_column($rows, 'niplama')))->pluck('id', 'nip_lama');
        foreach ($rows as $row) {
            $employeeId = $byNip->get($row['niplama']);
            if ($employeeId === null) {
                continue;
            }
            EmployeeRk::where('employee_id', $employeeId)->get(['id', 'name', 'project_id'])
                ->filter(fn (EmployeeRk $r) => self::norm($r->name) === self::norm($row['text']) && $r->project_id !== $project->id)
                ->each(fn (EmployeeRk $r) => $r->update(['project_id' => $project->id]));
        }
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
