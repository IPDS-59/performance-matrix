<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PlanItem;
use App\Models\SkpStatus;
use Carbon\Carbon;

/**
 * What a member needs on opening the app: is this week planned, did last week get its capaian, what is still not sent
 * to the atasan, and where the SKP stands.
 */
class BuildMemberHomeAction
{
    /** @return array<string, mixed> */
    public function execute(Employee $employee, Carbon $today): array
    {
        $monday = $today->copy()->startOfWeek(Carbon::MONDAY);
        $sunday = $monday->copy()->endOfWeek(Carbon::SUNDAY);
        $lastMonday = $monday->copy()->subWeek();
        $mine = fn () => PlanItem::where('employee_id', $employee->id);

        $thisWeek = $mine()->duringWeek($monday->toDateString(), $sunday->toDateString())->orderBy('date_start')->get(['description', 'date_start']);
        $lastWeek = $mine()->duringWeek($lastMonday->toDateString(), $lastMonday->copy()->endOfWeek(Carbon::SUNDAY)->toDateString())->get(['description', 'capaian']);
        $started = $mine()->duringWeek($today->copy()->startOfMonth()->toDateString(), $today->toDateString())
            ->whereDate('date_start', '<=', $today->toDateString())->get(['description', 'kip_sent_at']);

        $skp = SkpStatus::where('employee_id', $employee->id)->where('tahun', $today->year)->orderBy('kind')->orderBy('quarter')->get();
        $openSkp = $skp->reject(fn (SkpStatus $s) => stripos((string) $s->status_text, 'dinilai') !== false);

        return [
            'week' => ['start' => $monday->toDateString(), 'end' => $sunday->toDateString()],
            'plan' => [
                'count' => $thisWeek->count(),
                'items' => $thisWeek->take(5)->map(fn (PlanItem $p) => $p->description)->values()->all(),
            ],
            'last_week' => [
                'total' => $lastWeek->count(),
                'filled' => $lastWeek->whereNotNull('capaian')->count(),
                'missing' => $lastWeek->whereNull('capaian')->map(fn (PlanItem $p) => $p->description)->values()->all(),
            ],
            'sent' => [
                'total' => $started->count(),
                'sent' => $started->whereNotNull('kip_sent_at')->count(),
                'pending' => $started->whereNull('kip_sent_at')->map(fn (PlanItem $p) => $p->description)->values()->all(),
            ],
            'skp' => [
                'total' => $skp->count(),
                'open' => $openSkp->map(fn (SkpStatus $s) => $s->label().': '.($s->status_text ?? 'status belum diketahui'))->values()->all(),
            ],
        ];
    }
}
