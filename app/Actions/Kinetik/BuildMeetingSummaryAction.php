<?php

namespace App\Actions\Kinetik;

use App\Models\RecapLock;
use App\Models\RkRecap;
use App\Models\Team;
use App\Models\TeamRecapEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Per team, how far the weekly meetings and the monthly meeting of one month got: the PJ wrote a paraphrase, locked the
 * recap, attached a bukti rapat. A week still to come is "upcoming", not "none".
 */
class BuildMeetingSummaryAction
{
    /**
     * @param  Collection<int, Team>  $teams
     * @return array{weeks: list<array{start: string, label: string}>, teams: list<array<string, mixed>>}
     */
    public function execute(Collection $teams, int $year, int $month, CarbonImmutable $today): array
    {
        $first = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $starts = [];
        for ($monday = $first->startOfWeek(CarbonImmutable::MONDAY); $monday <= $first->endOfMonth(); $monday = $monday->addWeek()) {
            $starts[] = $monday->toDateString();
        }
        $thisMonday = $today->startOfWeek(CarbonImmutable::MONDAY)->toDateString();

        $recaps = RkRecap::whereIn('team_id', $teams->pluck('id'))
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('period_type', 'week')->whereIn('period_start', $starts))
                ->orWhere(fn ($m) => $m->where('period_type', 'month')->whereDate('period_start', $first->toDateString())))
            ->get(['team_id', 'period_type', 'period_start', 'paraphrase', 'locked_at'])
            ->groupBy(fn (RkRecap $r) => $r->team_id.'|'.$r->period_type.'|'.$r->period_start->toDateString());
        $locks = RecapLock::whereIn('team_id', $teams->pluck('id'))->get()
            ->mapWithKeys(fn (RecapLock $l) => [$l->team_id.'|'.$l->period_type.'|'.($l->period_type === 'week' ? $l->week_start?->toDateString() : $l->period_year.'-'.$l->period_month) => true]);
        $evidence = TeamRecapEvidence::whereIn('team_id', $teams->pluck('id'))
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('period_type', 'week')->whereDate('week_start', '>=', $starts[0])->whereDate('week_start', '<=', end($starts)))
                ->orWhere(fn ($m) => $m->where('period_type', 'month')->where('period_year', $year)->where('period_month', $month)))
            ->get(['team_id', 'period_type', 'week_start'])
            ->groupBy(fn (TeamRecapEvidence $e) => $e->team_id.'|'.$e->period_type.'|'.($e->period_type === 'week' ? $e->week_start->toDateString() : 'month'))
            ->map->count();

        $cell = function (Team $team, string $type, string $startDate, string $lockKey, bool $upcoming) use ($recaps, $locks, $evidence): array {
            $rows = $recaps->get($team->id.'|'.$type.'|'.$startDate, collect());
            $written = $rows->filter(fn (RkRecap $r) => trim((string) $r->paraphrase) !== '')->count();
            $locked = $locks->has($team->id.'|'.$type.'|'.$lockKey) || ($written > 0 && $rows->every(fn (RkRecap $r) => $r->locked_at !== null));

            return [
                'status' => $locked ? 'locked' : ($written > 0 ? 'written' : ($upcoming ? 'upcoming' : 'none')),
                'written' => $written,
                'evidence' => (int) $evidence->get($team->id.'|'.$type.'|'.($type === 'week' ? $startDate : 'month'), 0),
            ];
        };

        return [
            'weeks' => array_map(fn (string $s) => ['start' => $s, 'label' => CarbonImmutable::parse($s)->locale('id')->isoFormat('D MMM')], $starts),
            'teams' => $teams->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'leader' => $team->leader?->display_name ?? $team->leader?->name,
                'weeks' => array_map(fn (string $s) => ['start' => $s, ...$cell($team, 'week', $s, $s, $s > $thisMonday)], $starts),
                'month' => $cell($team, 'month', $first->toDateString(), $year.'-'.$month, $first > $today),
            ])->values()->all(),
        ];
    }
}
