<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PlanItem;
use App\Models\SkpStatus;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * The four questions the head asks on opening the app: who has no plan this week, whose kegiatan did not reach the
 * atasan yet, which team skipped last week's meeting, and which SKP wait for the head.
 */
class BuildHeadHomeAction
{
    public function __construct(private readonly BuildMeetingSummaryAction $meetings) {}

    /** @return array<string, mixed> */
    public function execute(Carbon $today, User $head): array
    {
        $monday = $today->copy()->startOfWeek(Carbon::MONDAY);
        $from = $monday->toDateString();
        $to = $monday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $members = Employee::where('is_active', true)->whereHas('teams')->orderBy('name')->get(['id', 'name', 'display_name']);
        $name = fn (Employee $e) => $e->display_name ?? $e->name;
        $plans = PlanItem::duringWeek($from, $to)->whereIn('employee_id', $members->pluck('id'))->get(['employee_id', 'kip_sent_at'])->groupBy('employee_id');

        $unsent = $members->filter(fn (Employee $e) => $plans->has($e->id) && $plans[$e->id]->contains(fn (PlanItem $p) => $p->kip_sent_at === null));

        return [
            'week' => ['start' => $from, 'end' => $to],
            'plans' => [
                'total' => $members->count(),
                'filled' => $members->filter(fn (Employee $e) => $plans->has($e->id))->count(),
                'missing' => $members->reject(fn (Employee $e) => $plans->has($e->id))->map($name)->values()->all(),
            ],
            'sent' => [
                'total' => $plans->count(),
                'sent' => $plans->count() - $unsent->count(),
                'pending' => $unsent->map($name)->values()->all(),
            ],
            'meetings' => $this->meetingsLastWeek($monday),
            'skp' => $this->skpWaiting($head),
        ];
    }

    /** @return array{week_label: string, total: int, missing: list<string>} */
    private function meetingsLastWeek(Carbon $monday): array
    {
        $last = CarbonImmutable::instance($monday)->subWeek();
        $summary = $this->meetings->execute(Team::orderBy('name')->get(), $last->year, $last->month, CarbonImmutable::instance($monday));
        $start = $last->toDateString();
        $missing = collect($summary['teams'])
            ->filter(fn (array $t) => collect($t['weeks'])->firstWhere('start', $start)['status'] === 'none')
            ->pluck('name')->values()->all();

        return ['week_label' => $last->locale('id')->isoFormat('D MMM'), 'total' => count($summary['teams']), 'missing' => $missing];
    }

    /** @return array{count: int, rows: list<array<string, mixed>>} */
    private function skpWaiting(User $head): array
    {
        $mine = $head->employee?->kip_pegawai_id;
        $rows = SkpStatus::with('employee:id,name,display_name')
            ->where(fn ($q) => $q->where(fn ($y) => $y->where('kind', 'yearly')->where('status_id', SkpStatus::YEARLY_WAITING))
                ->orWhere(fn ($t) => $t->where('kind', 'quarter')->where('status_id', SkpStatus::QUARTER_WAITING)))
            ->when($mine !== null, fn ($q) => $q->where('atasan_pegawai_id', (string) $mine))
            ->orderBy('status_changed_at')->get();

        return [
            'count' => $rows->count(),
            'rows' => $rows->take(6)->map(fn (SkpStatus $s) => [
                'employee' => $s->employee->display_name ?? $s->employee->name,
                'label' => $s->label(),
                'since' => $s->status_changed_at?->toDateString(),
            ])->values()->all(),
        ];
    }
}
