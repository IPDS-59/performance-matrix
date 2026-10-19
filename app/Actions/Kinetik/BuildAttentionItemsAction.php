<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Credit\CreditCalculator;
use App\Models\Employee;
use App\Models\PlanItem;
use App\Models\RkRecap;
use App\Models\Team;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * What the head or a PJ should look at first: kegiatan without a target, ended kegiatan without a
 * capaian, members without a plan this week, promotions near, last week's recap not locked, and
 * pushes to kipApp that failed. Only groups with something in them are returned, most urgent first.
 */
class BuildAttentionItemsAction
{
    private const SAMPLES = 5;

    public function __construct(private readonly CreditCalculator $credit) {}

    /**
     * @param  Collection<int, Team>  $teams
     * @return list<array{key: string, title: string, hint: string, severity: string, count: int, samples: list<string>, route: string}>
     */
    public function execute(Collection $teams, ?Carbon $today = null): array
    {
        $today ??= now();
        $teamIds = $teams->pluck('id');
        if ($teamIds->isEmpty()) {
            return [];
        }

        $items = PlanItem::whereIn('team_id', $teamIds)->where('status', '!=', 'cancelled')->with('employee:id,name,display_name');
        $name = fn (PlanItem $p) => ($p->employee?->display_name ?? $p->employee?->name ?? '-').': '.mb_strimwidth($p->description, 0, 60, '…');

        $missingTarget = (clone $items)->whereNull('target')
            ->whereDate('date_start', '>=', $today->copy()->startOfMonth()->toDateString())
            ->whereDate('date_start', '<=', $today->copy()->endOfMonth()->toDateString())->get();

        $missingCapaian = (clone $items)->whereNull('capaian')
            ->whereDate('date_end', '<', $today->toDateString())
            ->whereDate('date_end', '>=', $today->copy()->subDays(60)->toDateString())->get();

        $failed = (clone $items)->where('status', 'planned')->whereNotNull('push_error')->get();

        $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY)->toDateString();
        $planners = PlanItem::whereIn('team_id', $teamIds)->where('status', '!=', 'cancelled')
            ->whereDate('date_start', '<=', $weekEnd)->whereDate('date_end', '>=', $weekStart)->pluck('employee_id')->unique();
        $silent = Employee::where('is_active', true)->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))
            ->whereNotIn('id', $planners)->orderBy('name')->get();

        $promotions = Employee::with(['career', 'performanceRatings'])->where('is_active', true)
            ->whereHas('teams', fn ($q) => $q->whereIn('teams.id', $teamIds))->get()
            ->map(fn (Employee $e) => $this->credit->forEmployee($e, CarbonImmutable::parse($today->toDateString())))
            ->filter(fn (array $r) => in_array($r['status'], ['ready', 'ak_ready', 'near'], true))
            ->sortBy(fn (array $r) => ['ready' => 0, 'ak_ready' => 1, 'near' => 2][$r['status']])->values();

        $lastMonday = $today->copy()->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
        $lastWeekTeams = $teams->filter(function (Team $t) use ($lastMonday) {
            $has = PlanItem::where('team_id', $t->id)->where('status', '!=', 'cancelled')->whereDate('date_start', $lastMonday)->exists()
                || PlanItem::where('team_id', $t->id)->where('status', '!=', 'cancelled')
                    ->whereDate('date_start', '<=', Carbon::parse($lastMonday)->endOfWeek(Carbon::SUNDAY)->toDateString())->whereDate('date_end', '>=', $lastMonday)->exists();

            return $has && ! RkRecap::where('team_id', $t->id)->where('period_type', 'week')->whereDate('period_start', $lastMonday)->whereNotNull('locked_at')->exists();
        });

        $statusText = ['ready' => 'siap diusulkan', 'ak_ready' => 'AK cukup, menunggu syarat waktu', 'near' => 'hampir'];

        $groups = [
            ['missing_target', 'Kegiatan belum punya target', 'Bulan ini. Target diisi anggota di Rekap Mingguan.', 'high', $missingTarget->count(), $missingTarget->take(self::SAMPLES)->map($name)->all(), 'team-recap.weekly'],
            ['missing_capaian', 'Kegiatan selesai tanpa capaian', '60 hari terakhir. Capaian diisi saat rapat mingguan.', 'high', $missingCapaian->count(), $missingCapaian->take(self::SAMPLES)->map($name)->all(), 'team-recap.weekly'],
            ['failed_push', 'Belum terkirim ke kipApp', 'Alasannya tampil di rencana anggota.', 'high', $failed->count(), $failed->take(self::SAMPLES)->map($name)->all(), 'plan-compliance.index'],
            ['no_plan', 'Belum ada rencana minggu ini', 'Anggota tanpa satu pun kegiatan pada minggu berjalan.', 'high', $silent->count(), $silent->take(10)->map(fn (Employee $e) => $e->display_name ?? $e->name)->all(), 'plan-compliance.index'],
            ['recap_open', 'Rekap minggu lalu belum dikunci', 'Tim yang punya kegiatan minggu lalu tetapi belum ada RK terkunci.', 'medium', $lastWeekTeams->count(), $lastWeekTeams->take(self::SAMPLES)->map(fn (Team $t) => $t->name)->values()->all(), 'team-recap.weekly'],
            ['promotion', 'Kenaikan pangkat atau jenjang', 'Dari Angka Kredit: siap, AK cukup, atau hampir.', 'info', $promotions->count(), $promotions->take(self::SAMPLES)->map(fn (array $r) => $r['name'].': '.$statusText[$r['status']])->all(), 'credit.team'],
        ];

        $order = ['high' => 0, 'medium' => 1, 'info' => 2];

        return collect($groups)
            ->filter(fn (array $g) => $g[4] > 0)
            ->map(fn (array $g) => ['key' => $g[0], 'title' => $g[1], 'hint' => $g[2], 'severity' => $g[3], 'count' => $g[4], 'samples' => $g[5], 'route' => $g[6]])
            ->sortBy(fn (array $g) => $order[$g['severity']])->values()->all();
    }
}
