<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PlanItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The months of a year, or the weeks of a month, of one member: how many kegiatan they have,
 * how many carry a capaian, and how many were sent to the atasan. Only that member's own rows.
 */
class BuildMemberPeriodRowsAction
{
    /** @return list<array{key: int, label: string, current: bool, activities: int, written: int, locked: int}> */
    public function months(Employee $employee, int $year): array
    {
        $items = $this->items($employee, "{$year}-01-01", "{$year}-12-31")->groupBy(fn (PlanItem $p) => (int) Carbon::parse($p->date_start)->month);

        return collect(range(1, 12))->map(fn (int $m) => [
            'key' => $m,
            'label' => Carbon::create($year, $m, 1)->locale('id')->translatedFormat('F'),
            'current' => $year === now()->year && $m === now()->month,
            ...$this->counts($items[$m] ?? collect()),
        ])->all();
    }

    /** @return list<array{key: string, label: string, current: bool, activities: int, written: int, locked: int}> */
    public function weeks(Employee $employee, int $year, int $month): array
    {
        $first = Carbon::create($year, $month, 1)->startOfWeek(Carbon::MONDAY);
        $last = Carbon::create($year, $month, 1)->endOfMonth();
        $starts = [];
        for ($d = $first->copy(); $d->lte($last); $d->addWeek()) {
            $starts[] = $d->toDateString();
        }

        $items = $this->items($employee, $starts[0], Carbon::parse(end($starts))->endOfWeek(Carbon::SUNDAY)->toDateString())
            ->groupBy(fn (PlanItem $p) => Carbon::parse($p->date_start)->startOfWeek(Carbon::MONDAY)->toDateString());
        $thisWeek = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        return collect($starts)->map(fn (string $start) => [
            'key' => $start,
            'label' => Carbon::parse($start)->locale('id')->translatedFormat('j M').' – '.Carbon::parse($start)->endOfWeek(Carbon::SUNDAY)->locale('id')->translatedFormat('j M Y'),
            'current' => $start === $thisWeek,
            ...$this->counts($items[$start] ?? collect()),
        ])->all();
    }

    /** @return Collection<int, PlanItem> */
    private function items(Employee $employee, string $from, string $to)
    {
        return PlanItem::where('employee_id', $employee->id)->where('status', '!=', 'cancelled')
            ->whereDate('date_start', '>=', $from)->whereDate('date_start', '<=', $to)
            ->get(['id', 'date_start', 'capaian', 'kip_sent_at']);
    }

    /** @return array{activities: int, written: int, locked: int} */
    private function counts($items): array
    {
        return [
            'activities' => $items->count(),
            'written' => $items->filter(fn (PlanItem $p) => $p->capaian !== null)->count(),
            'locked' => $items->filter(fn (PlanItem $p) => $p->kip_sent_at !== null)->count(),
        ];
    }
}
