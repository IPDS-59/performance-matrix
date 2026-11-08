<?php

use App\Models\Employee;
use App\Models\FraIndicator;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\Kinetik\IkuProgressReport;

function ikuFixture(): array
{
    config(['kinetik.kip.tahun' => 2026]);
    $fra = FraIndicator::create(['year' => 2026, 'sort_order' => 1, 'tujuan' => 'T1', 'sasaran_code' => '1.1', 'sasaran_name' => 'S', 'code' => '1.1.1.1', 'name' => 'Persentase Publikasi Tepat Waktu', 'kind' => 'IKU', 'period_type' => 'percent', 'unit_type' => 'percent']);
    $pi = PerformanceIndicator::factory()->create(['year' => 2026, 'name' => 'Persentase  publikasi tepat waktu']);
    $teamA = Team::factory()->create(['name' => 'Tim A']);
    $teamB = Team::factory()->create(['name' => 'Tim B']);
    $projectA = Project::factory()->create(['team_id' => $teamA->id, 'performance_indicator_id' => $pi->id]);
    $projectB = Project::factory()->create(['team_id' => $teamB->id, 'performance_indicator_id' => $pi->id]);
    $rkA = PerformancePlan::factory()->create(['team_id' => $teamA->id, 'project_id' => $projectA->id]);
    $rkB = PerformancePlan::factory()->create(['team_id' => $teamB->id, 'project_id' => $projectB->id]);
    $employee = Employee::factory()->create();

    $item = fn (PerformancePlan $rk, Team $team, string $date, float $target, ?float $capaian, string $status = 'done') => PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $rk->id, 'description' => 'Kegiatan '.$date.$target, 'date_start' => $date, 'date_end' => $date,
        'target' => $target, 'capaian' => $capaian, 'status' => $status, 'source' => 'member',
    ]);

    return [$fra, $teamA, $teamB, $rkA, $rkB, $item];
}

it('rolls the capaian of kegiatan up to the IKU through RK and projek, capped at 100, and lists every team under it', function () {
    [$fra, , , $rkA, $rkB, $item] = ikuFixture();
    $item($rkA, Team::find($rkA->team_id), '2026-03-02', 10, 5);      // 50%, March
    $item($rkA, Team::find($rkA->team_id), '2026-03-09', 10, 10);     // 100%, March
    $item($rkB, Team::find($rkB->team_id), '2026-04-06', 4, 4);       // 100%, April
    $item($rkB, Team::find($rkB->team_id), '2026-04-07', 4, null);    // no capaian: not counted
    $item($rkB, Team::find($rkB->team_id), '2026-04-08', 4, 3, 'cancelled');

    $row = (new IkuProgressReport)->build(2026)['indicators'][0];

    expect($row['code'])->toBe('1.1.1.1')->and($row['linked'])->toBeTrue()
        ->and($row['teams'])->toBe(['Tim A', 'Tim B'])
        ->and($row['months'][3])->toBe(['value' => 75.0, 'items' => 2])
        ->and($row['months'][4])->toBe(['value' => 100.0, 'items' => 1])
        ->and($row['months'][5]['value'])->toBeNull()
        ->and($row['year_value'])->toBe(83.3)
        ->and($row['items'])->toBe(3);
});

it('breaks the same kegiatan down per team and marks an IKU with no IKU record', function () {
    [$fra, $teamA, $teamB, $rkA, , $item] = ikuFixture();
    $item($rkA, $teamA, '2026-03-02', 10, 5);
    FraIndicator::create(['year' => 2026, 'sort_order' => 2, 'tujuan' => 'T1', 'sasaran_code' => '1.2', 'sasaran_name' => 'S2', 'code' => '9.9.9.9', 'name' => 'IKU tanpa data master', 'kind' => 'IKU', 'period_type' => 'percent', 'unit_type' => 'percent']);

    $report = (new IkuProgressReport)->build(2026);

    expect($report['teams'])->toHaveCount(1)->and($report['teams'][0]['name'])->toBe('Tim A')
        ->and($report['teams'][0]['year_value'])->toBe(50.0)
        ->and($report['teams'][0]['ikus'])->toBe([['code' => '1.1.1.1', 'items' => 1]])
        ->and($report['indicators'][1]['linked'])->toBeFalse();
});

it('is readable by whoever can read the FRA and refused for a plain member', function () {
    ikuFixture();
    seedRolesAndPermissions();

    $this->actingAs(adminUser())->get(route('iku-progress.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Kinetik/IkuProgress')->has('indicators', 1));
    $member = User::factory()->create();
    $member->assignRole('staff');
    $this->actingAs($member)->get(route('iku-progress.index'))->assertForbidden();
});
