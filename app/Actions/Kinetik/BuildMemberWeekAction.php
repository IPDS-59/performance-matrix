<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
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
        return PlanItem::where('employee_id', $employee->id)
            ->duringWeek($monday->toDateString(), $monday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString())
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
        $plans = PerformancePlan::with('project', 'team')
            ->where(fn ($q) => $q->whereIn('team_id', $teamIds)->orWhereHas('project', fn ($p) => $p->whereIn('team_id', $teamIds)))
            ->orderBy('description')
            ->get();

        $own = EmployeeRk::where('employee_id', $employee->id)->where('year', $year)->pluck('name')->map(self::norm(...))->flip();
        $mine = $plans->filter(fn (PerformancePlan $p) => $own->has(self::norm($p->description)));
        $plans = $mine->isEmpty() ? $plans : $mine;

        $candidates = $this->links->candidates($plans);

        return $plans->map(fn (PerformancePlan $plan) => [
            'id' => $plan->id,
            'description' => $plan->description,
            'team_id' => $plan->project?->team_id ?? $plan->team_id,
            'team_name' => $plan->project?->team?->name ?? $plan->team?->name,
            'project_id' => $plan->project_id,
            'project_candidates' => $candidates->get($plan->id, []),
            'project_optional' => $this->links->projectOptional($plan),
            'target_unit' => $plan->target_unit,
        ])->values()->all();
    }

    /** @return list<array{id: int, name: string}> */
    private function projectOptions(Employee $employee): array
    {
        return Project::whereIn('team_id', $employee->teams()->pluck('teams.id'))
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
