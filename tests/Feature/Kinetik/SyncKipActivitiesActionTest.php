<?php

use App\Actions\Kinetik\BackfillRkAction;
use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Sources\MockKipActivitySource;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\Team;

it('creates kip_activity rows for employees with nip_lama', function () {
    $emp1 = Employee::factory()->create(['nip_lama' => '340060001']);
    $emp2 = Employee::factory()->create(['nip_lama' => '340060002']);

    $action = app(SyncKipActivitiesAction::class);
    $count = $action->execute(new MockKipActivitySource, collect([$emp1, $emp2]));

    // MockKipActivitySource returns 3 activities per employee
    expect($count)->toBe(6);
    expect(KipActivity::count())->toBe(6);
});

it('skips employees without nip_lama', function () {
    Employee::factory()->count(3)->create(['nip_lama' => null]);
    $withNip = Employee::factory()->create(['nip_lama' => '340060924']);

    $allEmployees = Employee::all();
    $action = app(SyncKipActivitiesAction::class);
    $count = $action->execute(new MockKipActivitySource, $allEmployees);

    // Only 1 employee has nip_lama → 3 activities from mock
    expect($count)->toBe(3);
    expect(KipActivity::count())->toBe(3);
});

it('is idempotent: re-running does not create duplicate rows', function () {
    $employee = Employee::factory()->create(['nip_lama' => '340060924']);
    $employees = collect([$employee]);
    $action = app(SyncKipActivitiesAction::class);

    $action->execute(new MockKipActivitySource, $employees);
    $countAfterFirst = KipActivity::count();

    $action->execute(new MockKipActivitySource, $employees);
    $countAfterSecond = KipActivity::count();

    expect($countAfterFirst)->toBe($countAfterSecond);
});

it('stores employee_id and nip_lama on each row', function () {
    $employee = Employee::factory()->create(['nip_lama' => '340060924']);
    $action = app(SyncKipActivitiesAction::class);
    $action->execute(new MockKipActivitySource, collect([$employee]));

    $activity = KipActivity::first();

    expect($activity->employee_id)->toBe($employee->id);
    expect($activity->nip_lama)->toBe('340060924');
});

it('stores raw_payload as array', function () {
    $employee = Employee::factory()->create(['nip_lama' => '340060924']);
    $action = app(SyncKipActivitiesAction::class);
    $action->execute(new MockKipActivitySource, collect([$employee]));

    $activity = KipActivity::first();

    expect($activity->raw_payload)->toBeArray();
});

it('persists new fields: rk_external_id, rk_name, progress, achievement_note, sent_at', function () {
    $employee = Employee::factory()->create(['nip_lama' => '340060924']);
    $action = app(SyncKipActivitiesAction::class);
    $action->execute(new MockKipActivitySource, collect([$employee]));

    $activity = KipActivity::first();

    expect($activity->rk_external_id)->toBe('mock-rk-340060924-001');
    expect($activity->rk_name)->toBe('Terlaksananya Penyusunan Laporan Bulanan BRS');
    expect($activity->progress)->toBe(100);
    expect($activity->achievement_note)->toBe('Laporan bulanan BRS selesai disusun');
    expect($activity->sent_at)->toBeNull();
});

it('persists period_id and source_year', function () {
    $employee = Employee::factory()->create(['nip_lama' => '340060924']);
    $action = app(SyncKipActivitiesAction::class);
    $action->execute(new MockKipActivitySource, collect([$employee]));

    $activity = KipActivity::first();

    expect($activity->period_id)->toBe('8');
    expect($activity->source_year)->toBe(2026);
});

it('backfills claimable RK (performance_plans) from synced activities', function () {
    $employee = Employee::factory()->create(['nip_lama' => '340060001']);

    app(SyncKipActivitiesAction::class)
        ->execute(new MockKipActivitySource, collect([$employee]));

    // Mock activities reference rkids -001 (x2) and -002 -> 2 distinct RKs.
    expect(PerformancePlan::whereNotNull('kip_external_id')->count())->toBe(2);

    $plan = PerformancePlan::where('kip_external_id', 'mock-rk-340060001-001')->first();
    expect($plan)->not->toBeNull()
        ->and($plan->team_id)->toBe($employee->team_id)
        ->and($plan->pic_employee_id)->toBe($employee->id);
});

it('BackfillRkAction deduplicates RK by (team_id, description) for two employees in the same team', function () {
    $team = Team::factory()->create();
    $emp1 = Employee::factory()->create(['nip_lama' => '340060001', 'team_id' => $team->id]);
    $emp2 = Employee::factory()->create(['nip_lama' => '340060002', 'team_id' => $team->id]);

    // Seed activities with the SAME rk_name but DIFFERENT rk_external_id.
    KipActivity::create([
        'employee_id' => $emp1->id,
        'nip_lama' => $emp1->nip_lama,
        'external_id' => 'act-001',
        'rk_external_id' => 'rk-emp1-001',
        'rk_name' => 'Pengelolaan Manajemen Risiko yang baik',
        'activity_date_start' => '2026-01-01',
        'description' => 'some activity',
        'source_year' => 2026,
    ]);
    KipActivity::create([
        'employee_id' => $emp2->id,
        'nip_lama' => $emp2->nip_lama,
        'external_id' => 'act-002',
        'rk_external_id' => 'rk-emp2-001', // different rkid, same name
        'rk_name' => 'Pengelolaan Manajemen Risiko yang baik',
        'activity_date_start' => '2026-01-02',
        'description' => 'some activity 2',
        'source_year' => 2026,
    ]);

    (new BackfillRkAction)->execute($emp1);
    (new BackfillRkAction)->execute($emp2);

    // Both employees share the same RK name in the same team → only 1 plan.
    expect(PerformancePlan::where('team_id', $team->id)->count())->toBe(1);
});
