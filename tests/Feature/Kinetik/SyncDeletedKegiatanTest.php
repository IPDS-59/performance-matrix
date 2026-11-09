<?php

use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Contracts\ReportsCompleteness;
use App\Kinetik\Data\KipActivityData;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use Illuminate\Support\Collection;

beforeEach(fn () => config(['kinetik.kip.tahun' => 2026]));

/** A source returning the given kegiatan ids, and saying whether that was every kegiatan of the period. */
function deletedSource(array $ids, bool $complete): KipActivitySource
{
    return new class($ids, $complete) implements KipActivitySource, ReportsCompleteness
    {
        public function __construct(private array $ids, private bool $complete) {}

        public function fetchActivities(string $nipLama, ?string $pegawaiId = null): Collection
        {
            return collect($this->ids)->map(fn (string $id) => KipActivityData::fromApiRow([
                'kegiatanperhariid' => $id, 'kegiatan' => 'Kegiatan '.$id, 'tanggal' => '2026-11-09', 'progres' => 0, 'tahun' => 2026,
            ]));
        }

        public function fetchPlans(string $nipLama): Collection
        {
            return collect();
        }

        public function fetchYearlyRks(string $pegawaiId): Collection
        {
            return collect();
        }

        public function lastFetchWasComplete(): bool
        {
            return $this->complete;
        }
    };
}

/** @return array{0: Employee, 1: PlanItem, 2: PlanItem} a member with two kegiatan in Kinetik and kipApp: ids 101 and 102 */
function twoLinkedKegiatan(): array
{
    $employee = Employee::factory()->create(['nip_lama' => '340099001', 'kip_pegawai_id' => null]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $items = collect(['101', '102'])->map(function (string $id) use ($employee, $team, $rk) {
        $activity = KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => $id, 'activity_date_start' => '2026-11-09', 'activity_date_end' => '2026-11-09']);

        return PlanItem::create([
            'team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $rk->id, 'description' => 'Memperbaiki aplikasi kinetik',
            'date_start' => '2026-11-09', 'date_end' => '2026-11-09', 'status' => 'pushed', 'kip_external_id' => $id, 'kip_activity_id' => $activity->id,
        ]);
    });

    return [$employee, $items[0], $items[1]];
}

it('cancels the plan of a kegiatan deleted in kipApp, so it stops showing after a sync', function () {
    [$employee, $kept, $deleted] = twoLinkedKegiatan();

    app(SyncKipActivitiesAction::class)->execute(deletedSource(['101'], complete: true), collect([$employee]));

    expect($deleted->fresh()->status)->toBe('cancelled');
    expect($kept->fresh()->status)->not->toBe('cancelled');
    expect(KipActivity::where('external_id', '102')->exists())->toBeFalse();
    expect(KipActivity::where('external_id', '101')->exists())->toBeTrue();
    expect(PlanItem::duringWeek('2026-11-09', '2026-11-15')->pluck('kip_external_id')->all())->toBe(['101']);
});

it('keeps every kegiatan when the fetch did not cover the whole period', function () {
    [$employee, , $deleted] = twoLinkedKegiatan();

    app(SyncKipActivitiesAction::class)->execute(deletedSource(['101'], complete: false), collect([$employee]));

    expect($deleted->fresh()->status)->toBe('pushed');
    expect(KipActivity::where('external_id', '102')->exists())->toBeTrue();
});

it('keeps every kegiatan when kipApp returned none at all, rather than wiping the member out', function () {
    [$employee, $kept, $deleted] = twoLinkedKegiatan();

    app(SyncKipActivitiesAction::class)->execute(deletedSource([], complete: true), collect([$employee]));

    expect($kept->fresh()->status)->toBe('pushed');
    expect($deleted->fresh()->status)->toBe('pushed');
    expect(KipActivity::count())->toBe(2);
});

it('leaves kegiatan of another year alone, since only the configured period is read', function () {
    [$employee] = twoLinkedKegiatan();
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => '900', 'activity_date_start' => '2025-12-30', 'activity_date_end' => '2025-12-30']);

    app(SyncKipActivitiesAction::class)->execute(deletedSource(['101', '102'], complete: true), collect([$employee]));

    expect(KipActivity::where('external_id', '900')->exists())->toBeTrue();
});

it('does not touch a plan sent to kipApp after the fetch, whose kegiatan is not stored yet', function () {
    [$employee, , $deleted] = twoLinkedKegiatan();
    $fresh = PlanItem::create([...$deleted->only(['team_id', 'employee_id', 'performance_plan_id', 'description', 'date_start', 'date_end']), 'status' => 'pushed', 'kip_external_id' => '555']);

    app(SyncKipActivitiesAction::class)->execute(deletedSource(['101', '102'], complete: true), collect([$employee]));

    expect($fresh->fresh()->status)->toBe('pushed');
});
