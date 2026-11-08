<?php

namespace App\Services\Kinetik;

use App\Models\Employee;
use App\Models\FraIndicator;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Progress of the work under each IKU: the capaian members filled in against the target of their kegiatan, rolled up through
 * kegiatan, RK, projek to the IKU. This is how far the planned work is, not the IKU's realisation: an IKU has its own base data
 * and formula (publications made over planned, an index score), which no RK capaian can give. The sheet's FRA stays the record.
 *
 * Each kegiatan counts as capaian ÷ target (at most 100%); a month or a year is the plain average of the kegiatan in it.
 * An IKU belongs to every team that has a projek under it, so one IKU can list several teams.
 */
class IkuProgressReport
{
    /**
     * @return array{year: int, indicators: list<array<string, mixed>>, teams: list<array<string, mixed>>}
     */
    public function build(int $year): array
    {
        $fra = FraIndicator::where('year', $year)->orderBy('sort_order')->get();
        $pis = PerformanceIndicator::where('year', $year)->get()->keyBy(fn (PerformanceIndicator $p) => self::norm($p->name));

        $projectsByPi = Project::with('team:id,name')->whereIn('performance_indicator_id', $pis->pluck('id'))->get(['id', 'name', 'team_id', 'performance_indicator_id'])->groupBy('performance_indicator_id');
        $plansByProject = PerformancePlan::whereIn('project_id', $projectsByPi->flatten()->pluck('id'))->get(['id', 'project_id'])->groupBy('project_id');

        $items = PlanItem::where('status', '!=', 'cancelled')->where('target', '>', 0)->whereNotNull('capaian')
            ->whereYear('date_start', $year)
            ->get(['id', 'team_id', 'performance_plan_id', 'project_id', 'date_start', 'target', 'capaian']);
        $itemsByPlan = $items->groupBy('performance_plan_id');
        $itemsByProject = $items->whereNotNull('project_id')->groupBy('project_id');

        $teamNames = Team::pluck('name', 'id');
        $teamBuckets = [];
        $rows = $fra->map(function (FraIndicator $f) use ($pis, $projectsByPi, $plansByProject, $itemsByPlan, $itemsByProject, $teamNames, &$teamBuckets) {
            $pi = $pis->get(self::norm($f->name));
            $projects = $pi ? $projectsByPi->get($pi->id, collect()) : collect();
            $mine = $projects->flatMap(fn (Project $p) => $plansByProject->get($p->id, collect())->flatMap(fn (PerformancePlan $plan) => $itemsByPlan->get($plan->id, collect())))
                ->merge($projects->flatMap(fn (Project $p) => $itemsByProject->get($p->id, collect())))
                ->unique('id')->values();

            foreach ($mine->groupBy('team_id') as $teamId => $teamItems) {
                $teamBuckets[$teamId]['name'] = $teamNames->get($teamId);
                $teamBuckets[$teamId]['items'] = ($teamBuckets[$teamId]['items'] ?? collect())->merge($teamItems);
                $teamBuckets[$teamId]['ikus'][$f->code] = ($teamBuckets[$teamId]['ikus'][$f->code] ?? 0) + $teamItems->count();
            }

            return [
                'id' => $f->id,
                'code' => $f->code,
                'name' => $f->name,
                'sasaran_code' => $f->sasaran_code,
                'linked' => $pi !== null,
                'projects' => $projects->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name, 'team' => $p->team?->name])->values()->all(),
                'teams' => $projects->pluck('team.name')->filter()->unique()->sort()->values()->all(),
                ...$this->aggregate($mine),
            ];
        })->values()->all();

        // Every active team is listed, also one with nothing to show yet, with what is missing and from whom: a team does not appear in
        // the figures until its kegiatan have a target and a capaian, and that is the thing to follow up.
        $coverage = $this->coverage($year);
        $teams = Team::where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(function (Team $team) use ($teamBuckets, $coverage) {
            $bucket = $teamBuckets[$team->id] ?? null;

            return [
                'id' => $team->id,
                'name' => $team->name,
                'ikus' => $bucket ? collect($bucket['ikus'])->map(fn (int $n, string $code) => ['code' => $code, 'items' => $n])->values()->all() : [],
                ...$this->aggregate($bucket['items'] ?? collect()),
                'coverage' => $coverage[$team->id] ?? ['kegiatan' => 0, 'with_target' => 0, 'filled' => 0, 'people' => []],
            ];
        })->values()->all();

        return ['year' => $year, 'indicators' => $rows, 'teams' => $teams];
    }

    /**
     * Per team: kegiatan of the year, how many have a target, how many have target and capaian, and the people with the most missing.
     *
     * @return array<int, array{kegiatan: int, with_target: int, filled: int, people: list<array{id: int, name: string, missing: int}>}>
     */
    private function coverage(int $year): array
    {
        $names = Employee::pluck(DB::raw('coalesce(display_name, name)'), 'id');
        $rows = PlanItem::where('status', '!=', 'cancelled')->whereYear('date_start', $year)->whereNotNull('team_id')
            ->get(['team_id', 'employee_id', 'target', 'capaian'])->groupBy('team_id');

        return $rows->map(function (Collection $items) use ($names) {
            $filled = fn ($i) => (float) $i->target > 0 && $i->capaian !== null;

            return [
                'kegiatan' => $items->count(),
                'with_target' => $items->filter(fn ($i) => (float) $i->target > 0)->count(),
                'filled' => $items->filter($filled)->count(),
                'people' => $items->reject($filled)->groupBy('employee_id')->map(fn (Collection $m, $id) => ['id' => (int) $id, 'name' => (string) ($names[$id] ?? '-'), 'missing' => $m->count()])
                    ->sortByDesc('missing')->values()->all(),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, PlanItem>  $items
     * @return array{months: array<int, array{value: float|null, items: int}>, year_value: float|null, items: int}
     */
    private function aggregate(Collection $items): array
    {
        $pct = fn (PlanItem $i) => min(100.0, (float) $i->capaian / (float) $i->target * 100);
        $byMonth = $items->groupBy(fn (PlanItem $i) => (int) Carbon::parse($i->date_start)->month);

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $in = $byMonth->get($m, collect());
            $months[$m] = ['value' => $in->isEmpty() ? null : round($in->avg($pct), 1), 'items' => $in->count()];
        }

        return ['months' => $months, 'year_value' => $items->isEmpty() ? null : round($items->avg($pct), 1), 'items' => $items->count()];
    }

    private static function norm(?string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
    }
}
