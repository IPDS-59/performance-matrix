<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\WeeklyFocus;
use App\Services\Kinetik\PlanItemAccess;
use Carbon\Carbon;

/**
 * Data of "Rekap Mingguan" for one member: last week's capaian, this week's
 * plan and the RK / Projek the add form can pick. Always one member only.
 */
class BuildMemberWeekAction
{
    public function __construct(private readonly LinkPlansToProjectsAction $links, private readonly PlanItemAccess $access) {}

    /** @return array<string, mixed> */
    public function execute(Employee $employee, Carbon $anchor): array
    {
        $monday = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $lastMonday = $monday->copy()->subWeek();

        return [
            'weekStart' => $monday->toDateString(),
            'weekEnd' => $monday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            'lastWeekStart' => $lastMonday->toDateString(),
            'lastWeekEnd' => $lastMonday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString(),
            'prevWeek' => $lastMonday->toDateString(),
            'nextWeek' => $monday->copy()->addWeek()->toDateString(),
            'lastWeek' => $this->rows($employee, $lastMonday),
            'thisWeek' => $this->rows($employee, $monday),
            'rkOptions' => $this->rkOptions($employee, $monday->year),
            'projectOptions' => $this->projectOptions($employee),
            'focus' => WeeklyFocus::where('employee_id', $employee->id)->whereDate('week_start', $monday->toDateString())->value('body'),
            'rkSynced' => EmployeeRk::where('employee_id', $employee->id)->exists(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function rows(Employee $employee, Carbon $monday): array
    {
        return $this->rowsBetween($employee, $monday->toDateString(), $monday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString());
    }

    /**
     * The member's kegiatan that overlap the dates, in the shape of the Rekap Mingguan tables (what the member may still do with each
     * one comes from the settings and the SKP status). Also read by the quarterly "Kirim ke atasan" page.
     *
     * @return list<array<string, mixed>>
     */
    public function rowsBetween(Employee $employee, string $from, string $to): array
    {
        return PlanItem::where('employee_id', $employee->id)
            ->duringWeek($from, $to)
            ->with('performancePlan:id,description')
            ->orderBy('date_start')
            ->orderBy('id')
            ->get()
            ->map(fn (PlanItem $p) => [
                'id' => $p->id,
                'team_id' => $p->team_id,
                'description' => $p->description,
                'performance_plan_id' => $p->performance_plan_id,
                'rk_name' => $p->performancePlan?->description,
                'project_id' => $p->project_id,
                'date_start' => Carbon::parse($p->date_start)->toDateString(),
                'date_end' => Carbon::parse($p->date_end)->toDateString(),
                'time_start' => $p->time_start ? substr((string) $p->time_start, 0, 5) : null,
                'time_end' => $p->time_end ? substr((string) $p->time_end, 0, 5) : null,
                'target' => $p->target !== null ? (float) $p->target : null,
                'target_unit' => $p->target_unit,
                'capaian' => $p->capaian !== null ? (float) $p->capaian : null,
                'capaian_note' => $p->capaian_note,
                'evidence_url' => $p->evidence_url,
                'progress' => (int) $p->progress,
                'status' => $p->status,
                'source' => $p->source,
                'push_error' => $p->status === 'planned' ? $p->push_error : null,
                'in_kipapp' => $p->kip_external_id !== null,
                'sent' => $p->kip_sent_at !== null,
                'sent_at' => $p->kip_sent_at?->toDateString(),
                'is_capaian_skp' => (bool) $p->is_capaian_skp,
                // What the member may still do, from the settings and the SKP status (PlanItemAccess).
                'startable' => $this->access->hasStarted($p),
                'can_edit' => $this->access->canEdit($p),
                'can_delete' => $this->access->canDelete($p),
                'can_send' => $this->access->canSend($p),
                'lock_reason' => $this->access->lockReason($p),
            ])
            ->values()
            ->all();
    }

    /**
     * The RK of the member's teams. When the member's own RK list from kipApp is
     * known, only those are offered; otherwise every RK of the teams.
     *
     * @return list<array<string, mixed>>
     */
    private function rkOptions(Employee $employee, int $year): array
    {
        $teamIds = $employee->teams()->pluck('teams.id');
        // The RK the member's kegiatan already sit under always stay offered (an Excel import may have put them under an RK that is not
        // in the member's own kipApp list), or the form of such a kegiatan shows an empty RK.
        $used = PlanItem::where('employee_id', $employee->id)->whereNotNull('performance_plan_id')->distinct()->pluck('performance_plan_id');
        $plans = PerformancePlan::with('project', 'team')
            ->where(fn ($q) => $q->whereIn('team_id', $teamIds)->orWhereHas('project', fn ($p) => $p->whereIn('team_id', $teamIds))->orWhere('owner_employee_id', $employee->id)->orWhereIn('id', $used))
            ->where(fn ($q) => $q->where('is_additional', false)->orWhere('owner_employee_id', $employee->id))
            ->orderBy('description')
            ->get();

        $own = EmployeeRk::where('employee_id', $employee->id)->where('year', $year)->pluck('name')->map(self::norm(...))->flip();
        $mine = $plans->filter(fn (PerformancePlan $p) => $own->has(self::norm($p->description)));
        $plans = $mine->isEmpty() ? $plans : $mine->merge($plans->where('owner_employee_id', $employee->id))->merge($plans->whereIn('id', $used))->unique('id');

        // kipApp copies an RK for every member and every quarter SKP, so one RK text becomes several local rows (one head of
        // several teams saw 41 rows for 9 RK). Show each RK once per projek (or per team when it has none), preferring the
        // row of the member's own team, then the oldest.
        $key = fn (PerformancePlan $p) => ($p->project_id ?? 't'.$p->team_id).'|'.self::norm($p->description);
        $sorted = $plans->sortBy(fn (PerformancePlan $p) => [$used->contains($p->id) ? 0 : 1, $teamIds->contains($p->team_id) ? 0 : 1, $p->id]);
        // The copies folded into one option: a kegiatan filed under a copy still finds its RK.
        $aliases = $sorted->groupBy($key)->map(fn ($g) => $g->skip(1)->pluck('id')->values()->all());
        $plans = $sorted->unique($key)->sortBy('description')->values();

        // An RK the member owns only because no team plan carried it yet (see SyncKipActivitiesAction::ensureExternalPlans) steps aside
        // once a team plan with the same text exists, so the RK is not listed twice.
        $teamTexts = $plans->filter(fn (PerformancePlan $p) => $p->owner_employee_id === null)->map(fn (PerformancePlan $p) => self::norm($p->description))->flip();
        $plans = $plans->reject(fn (PerformancePlan $p) => $p->owner_employee_id !== null && ! $p->is_additional && $teamTexts->has(self::norm($p->description)))->values();

        // An RK of a team Kinetik does not hold (a Pusat team) belongs to no team here, but every kegiatan needs one:
        // it is filed under the member's own team (primary first), as the Excel import does.
        $homeTeamId = $employee->teams()->orderByDesc('employee_team.is_primary')->value('teams.id');
        $candidates = $this->links->candidatesFor($plans, $employee);
        // A team leader's RK is the RK Ketua of their projek: it belongs under each projek whose RK Ketua carries its text.
        $ketuaProjects = Project::whereNotNull('leader_rk')->get(['id', 'leader_rk'])->groupBy(fn (Project $p) => self::norm($p->leader_rk))->map(fn ($g) => $g->pluck('id')->values()->all());

        return $plans->map(fn (PerformancePlan $plan) => [
            'id' => $plan->id,
            'alias_ids' => $aliases->get($key($plan), []),
            'description' => $plan->description,
            'team_id' => $plan->project?->team_id ?? $plan->team_id ?? ($plan->owner_employee_id === $employee->id ? $homeTeamId : null),
            'team_name' => $plan->project?->team?->name ?? $plan->team?->name ?? ($plan->owner_employee_id !== null && ! $plan->is_additional ? ($plan->external_team_name ?? 'Di luar tim Sulteng') : null),
            'project_id' => $plan->project_id,
            'external_project' => $plan->external_project_name,
            'project_candidates' => $candidates->get($plan->id, []),
            'is_additional' => (bool) $plan->is_additional,
            'ketua_projects' => $ketuaProjects->get(self::norm($plan->description), []),
            'project_suggested' => count($candidates->get($plan->id, [])) === 1 ? $candidates->get($plan->id)[0] : null,
            'project_optional' => $this->links->projectOptionalFor($plan, $employee),
            'target_unit' => $plan->target_unit,
        ])->values()->all();
    }

    /**
     * The projek of the member's teams the member can file under: one they are a member of, lead, or hold an RK in, any projek of a
     * team they lead, and a projek whose members are not known. A projek of the same team the member has no part in only led to an
     * empty RK list.
     *
     * @return list<array{id: int, name: string}>
     */
    private function projectOptions(Employee $employee): array
    {
        $teamIds = $employee->teams()->pluck('teams.id');
        $ledTeamIds = $employee->ledTeams()->pluck('teams.id')->merge(Team::where('leader_id', $employee->id)->pluck('id'));
        $ownProjectIds = EmployeeRk::where('employee_id', $employee->id)->whereNotNull('project_id')->pluck('project_id');

        return Project::whereIn('team_id', $teamIds)
            ->where(fn ($q) => $q->whereHas('members', fn ($m) => $m->where('employees.id', $employee->id))
                ->orWhere('leader_id', $employee->id)
                ->orWhereIn('team_id', $ledTeamIds)
                ->orWhereIn('id', $ownProjectIds)
                ->orWhereDoesntHave('members'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Project $p) => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }

    private static function norm(?string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
    }
}
