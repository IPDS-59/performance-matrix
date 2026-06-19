<?php

use App\Actions\Kinetik\SyncKipPlansAction;
use App\Kinetik\Auth\ConfigBearerAuthenticator;
use App\Kinetik\Sources\ApiKipStructureSource;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'kinetik.kip.base_url' => 'https://kipapp.bps.go.id/api',
        'kinetik.kip.token' => 'test-token-abc',
        'kinetik.kip.timeout' => 15,
    ]);
});

function fakeCascade(): void
{
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['kegiatan' => [['skpid' => '1285321', 'jumlahkegiatan' => 1]]],
        ], 200),
        'kipapp.bps.go.id/api/v1/skp/rk*' => Http::response([
            ['rkid' => '13962139', 'rencanakinerja' => 'Terlaksananya Dukungan Metodologi', 'timkerjaid' => '106453'],
        ], 200),
        'kipapp.bps.go.id/api/v1/skp/iki*' => Http::response([
            ['ikiid' => '1', 'rkid' => '13962139', 'iki' => 'Persentase Dukungan Metodologi: 100%'],
        ], 200),
    ]);
}

it('enriches RK with the team and parsed target from the cascade', function () {
    fakeCascade();
    $team = Team::factory()->create(['kip_external_id' => '106453', 'name' => 'MTI']);
    $employee = Employee::factory()->create(['nip_lama' => '340060924']);

    $summary = (new SyncKipPlansAction)->execute(
        new ApiKipStructureSource(new ConfigBearerAuthenticator),
        collect([$employee]),
    );

    expect($summary['created'])->toBe(1);

    $plan = PerformancePlan::where('kip_external_id', '13962139')->first();
    expect($plan)->not->toBeNull()
        ->and($plan->team_id)->toBe($team->id)
        ->and($plan->description)->toBe('Terlaksananya Dukungan Metodologi')
        ->and((float) $plan->target)->toBe(100.0)
        ->and($plan->target_unit)->toBe('%');
});

it('enriches an existing backfilled RK without duplicating it', function () {
    fakeCascade();
    Team::factory()->create(['kip_external_id' => '106453']);
    $employee = Employee::factory()->create(['nip_lama' => '340060924']);

    // Pre-existing backfilled RK (no target yet).
    PerformancePlan::create([
        'kip_external_id' => '13962139',
        'description' => 'old desc',
        'period_type' => 'year',
    ]);

    $summary = (new SyncKipPlansAction)->execute(
        new ApiKipStructureSource(new ConfigBearerAuthenticator),
        collect([$employee]),
    );

    expect($summary['enriched'])->toBe(1)
        ->and(PerformancePlan::where('kip_external_id', '13962139')->count())->toBe(1)
        ->and((float) PerformancePlan::where('kip_external_id', '13962139')->first()->target)->toBe(100.0);
});

it('deduplicates RK by (team_id, description) when two employees have the same plan name', function () {
    $team = Team::factory()->create(['kip_external_id' => '106453', 'name' => 'MTI']);
    $emp1 = Employee::factory()->create(['nip_lama' => '340060924', 'team_id' => $team->id]);
    $emp2 = Employee::factory()->create(['nip_lama' => '340060925', 'team_id' => $team->id]);

    // Both employees return the same RK name but different rkids.
    // Use a sequence so the second call to skp/rk returns a different rkid.
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::sequence()
            ->push([['kegiatan' => [['skpid' => '1285321', 'jumlahkegiatan' => 1]]]], 200)
            ->push([['kegiatan' => [['skpid' => '1285322', 'jumlahkegiatan' => 1]]]], 200),
        'kipapp.bps.go.id/api/v1/skp/rk*' => Http::sequence()
            ->push([['rkid' => '13962139', 'rencanakinerja' => 'Terlaksananya Dukungan Metodologi', 'timkerjaid' => '106453']], 200)
            ->push([['rkid' => '13999999', 'rencanakinerja' => 'Terlaksananya Dukungan Metodologi', 'timkerjaid' => '106453']], 200),
        'kipapp.bps.go.id/api/v1/skp/iki*' => Http::response([], 200),
    ]);

    $summary = (new SyncKipPlansAction)->execute(
        new ApiKipStructureSource(new ConfigBearerAuthenticator),
        collect([$emp1, $emp2]),
    );

    // Second employee reuses the existing plan — only 1 plan for this team+description.
    expect(PerformancePlan::where('team_id', $team->id)->count())->toBe(1)
        ->and($summary['created'])->toBe(1)
        ->and($summary['enriched'])->toBe(1);
});
