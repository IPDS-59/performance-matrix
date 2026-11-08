<?php

use App\Actions\Kinetik\BuildMemberWeekAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Data\KipRkData;
use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\MemberKipCredential;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRolesAndPermissions();
    Carbon::setTestNow('2026-10-08 06:00:00');
    config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'kinetik.kip.tahun' => 2026, 'kinetik.kip.periode_id' => 8]);
});
afterEach(fn () => Carbon::setTestNow());

function memberWithTeam(): array
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => '777']);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    MemberKipCredential::remember($user, 'sso-pass', 'member-token', now()->addHours(5));

    return [$user, $employee, $team];
}

it('gives an RK tambahan from the member\'s own kipApp SKP a plan row owned by them', function () {
    [, $employee, $team] = memberWithTeam();

    $sync = app(SyncKipActivitiesAction::class);
    $method = new ReflectionMethod($sync, 'rememberEmployeeRks');
    $method->setAccessible(true);
    $method->invoke($sync, $employee, collect([
        KipRkData::fromApiRow(['rkid' => '9001', 'rencanakinerja' => 'Terlaksananya kegiatan di Bagian Umum yang diikuti dengan baik', 'jeniskinerja' => 2, 'tahun' => 2026]),
        KipRkData::fromApiRow(['rkid' => '9002', 'rencanakinerja' => 'RK biasa dari tim', 'jeniskinerja' => 1, 'tahun' => 2026, 'timkerjaid' => '1']),
    ]));

    expect(EmployeeRk::where('employee_id', $employee->id)->where('is_additional', true)->count())->toBe(1);
    $plan = PerformancePlan::where('owner_employee_id', $employee->id)->where('is_additional', true)->first();
    expect($plan)->is_additional->toBeTrue()->team_id->toBe($team->id)->kip_external_id->toBe('9001');

    // the same sync again adds nothing
    $method->invoke($sync, $employee, collect([KipRkData::fromApiRow(['rkid' => '9001', 'rencanakinerja' => 'Terlaksananya kegiatan di Bagian Umum yang diikuti dengan baik', 'jeniskinerja' => 2, 'tahun' => 2026])]));
    expect(PerformancePlan::where('owner_employee_id', $employee->id)->where('is_additional', true)->count())->toBe(1);
});

it('offers the member\'s RK tambahan without a projek, and nobody else\'s', function () {
    [$user, $employee, $team] = memberWithTeam();
    [, $other] = memberWithTeam();
    EmployeeRk::create(['employee_id' => $employee->id, 'kip_rk_id' => '1', 'name' => 'RK tim', 'year' => 2026]);
    PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'RK tim']);
    PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'RK tambahan saya', 'is_additional' => true, 'owner_employee_id' => $employee->id]);
    PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'RK tambahan orang lain', 'is_additional' => true, 'owner_employee_id' => $other->id]);

    $this->actingAs($user)->get(route('weekly.index', ['week' => '2026-10-07']))->assertInertia(fn ($p) => $p
        ->has('rkOptions', 2)
        ->where('rkOptions', fn ($opts) => collect($opts)->pluck('description')->sort()->values()->all() === ['RK tambahan saya', 'RK tim'])
        ->where('rkOptions', fn ($opts) => collect($opts)->firstWhere('description', 'RK tambahan saya')['project_optional'] === true));
});

it('adds an RK tambahan from the form once per text', function () {
    [$user, $employee] = memberWithTeam();

    $first = $this->actingAs($user)->postJson(route('rk-additional.store'), ['description' => 'Menyiapkan  bahan rapat koordinasi tambahan'])->assertOk()->json();
    $again = $this->actingAs($user)->postJson(route('rk-additional.store'), ['description' => 'menyiapkan bahan rapat koordinasi tambahan'])->assertOk()->json();
    $this->actingAs($user)->postJson(route('rk-additional.store'), ['description' => 'pendek'])->assertUnprocessable();

    expect($again['id'])->toBe($first['id'])->and(PerformancePlan::where('owner_employee_id', $employee->id)->count())->toBe(1);
});

it('holds a kegiatan under a new RK tambahan back from kipApp until the switch is on, then creates it the way kipApp does', function () {
    [, $employee, $team] = memberWithTeam();
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'kip_external_id' => null, 'description' => 'Menyiapkan bahan rapat koordinasi tambahan', 'is_additional' => true, 'owner_employee_id' => $employee->id]);
    $item = PlanItem::create(['team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $rk->id, 'description' => 'Menyusun bahan rapat', 'date_start' => '2026-10-08', 'date_end' => '2026-10-08']);
    Http::fake([
        '*/v1/skp/rk*' => Http::sequence([Http::response([]), Http::response([['rkid' => '15000001', 'rencanakinerja' => 'Menyiapkan bahan rapat koordinasi tambahan']])]),
        '*/v1/skp*' => Http::response([['id' => '1460709', 'periodeid' => 8, 'periodepenilaianid' => 4, 'statusskpid' => 1, 'statusskp' => 'Sedang dibuat']]),
        '*/v1/kegiatan*' => Http::sequence([Http::response([]), Http::response(['status' => true]), Http::response([['kegiatanperhariid' => 6660001, 'kegiatan' => 'Menyusun bahan rapat']])]),
    ]);

    config(['kinetik.kip.rk_tambahan_write' => false]);
    expect(app(PushPlanItemAction::class)->execute($item))->toBeFalse();
    expect($item->fresh()->push_error)->toContain('belum ada di kipApp');
    Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'v1/kegiatan'));
});

it('gives an RK of a team Kinetik does not hold (a Pusat team) a plan row owned by the member, and lists it once', function () {
    [, $employee, $team] = memberWithTeam();
    $sync = app(SyncKipActivitiesAction::class);
    $method = new ReflectionMethod($sync, 'rememberEmployeeRks');
    $method->setAccessible(true);
    $rows = collect([
        KipRkData::fromApiRow(['rkid' => '7001', 'rencanakinerja' => 'Terselenggaranya Layanan Kesehatan Mental Untuk Pegawai', 'jeniskinerja' => 1, 'tahun' => 2026, 'timkerjaid' => '62386']),
    ]);
    $method->invoke($sync, $employee, $rows);
    $method->invoke($sync, $employee, $rows);

    $plans = PerformancePlan::where('owner_employee_id', $employee->id)->get();
    expect($plans)->toHaveCount(1)->and($plans[0])->is_additional->toBeFalse()->team_id->toBeNull()->kip_external_id->toBe('7001');

    $week = app(BuildMemberWeekAction::class);
    $options = (new ReflectionMethod($week, 'rkOptions'))->invoke($week, $employee, 2026);
    expect(collect($options)->where('id', $plans[0]->id))->toHaveCount(1)
        ->and(collect($options)->firstWhere('id', $plans[0]->id)['team_name'])->toBe('Di luar tim Sulteng');

    // once a team plan carries the same text, the member's own copy steps aside
    PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'Terselenggaranya Layanan Kesehatan Mental Untuk Pegawai']);
    $again = (new ReflectionMethod($week, 'rkOptions'))->invoke($week, $employee, 2026);
    expect(collect($again)->where('description', 'Terselenggaranya Layanan Kesehatan Mental Untuk Pegawai'))->toHaveCount(1);
});

it('offers only the projek of the member\'s teams that the member has a part in', function () {
    [, $employee, $team] = memberWithTeam();
    $other = Employee::factory()->create();
    $mine = Project::factory()->create(['team_id' => $team->id, 'name' => 'Projek saya']);
    $mine->members()->attach($employee->id, ['role' => 'member']);
    $notMine = Project::factory()->create(['team_id' => $team->id, 'name' => 'Projek orang lain']);
    $notMine->members()->attach($other->id, ['role' => 'member']);
    $unknown = Project::factory()->create(['team_id' => $team->id, 'name' => 'Projek tanpa anggota']);
    $viaRk = Project::factory()->create(['team_id' => $team->id, 'name' => 'Projek lewat RK']);
    $viaRk->members()->attach($other->id, ['role' => 'member']);
    EmployeeRk::create(['employee_id' => $employee->id, 'kip_rk_id' => '1', 'name' => 'RK', 'project_id' => $viaRk->id, 'year' => 2026]);

    $week = app(BuildMemberWeekAction::class);
    $names = fn (Employee $e) => collect((new ReflectionMethod($week, 'projectOptions'))->invoke($week, $e))->pluck('name')->sort()->values()->all();

    expect($names($employee))->toBe(['Projek lewat RK', 'Projek saya', 'Projek tanpa anggota']);

    // the leader of the team files under any projek of it
    $employee->teams()->updateExistingPivot($team->id, ['role' => 'leader']);
    expect($names($employee->fresh()))->toBe(['Projek lewat RK', 'Projek orang lain', 'Projek saya', 'Projek tanpa anggota']);
});

it('creates a new RK tambahan in the SKP with POST v1/skp/rk, then files the kegiatan under the rkid kipApp gave it', function () {
    [, $employee, $team] = memberWithTeam();
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'kip_external_id' => null, 'description' => 'Menyiapkan bahan rapat koordinasi tambahan', 'is_additional' => true, 'owner_employee_id' => $employee->id]);
    $item = PlanItem::create(['team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $rk->id, 'description' => 'Menyusun bahan rapat', 'date_start' => '2026-10-08', 'date_end' => '2026-10-08']);
    Http::fake([
        '*/v1/skp/rk*' => Http::sequence([
            Http::response([]),
            Http::response(['status' => true, 'message' => 'Successfully create/edit data']),
            Http::response([['rkid' => '15000001', 'rencanakinerja' => 'Menyiapkan bahan rapat koordinasi tambahan']]),
        ]),
        '*/v1/skp*' => Http::response([['id' => '1460709', 'periodeid' => 8, 'periodepenilaianid' => 4, 'statusskpid' => 1, 'statusskp' => 'Sedang dibuat']]),
        '*/v1/kegiatan*' => Http::sequence([Http::response([]), Http::response(['status' => true]), Http::response([['kegiatanperhariid' => 6660001, 'kegiatan' => 'Menyusun bahan rapat']])]),
    ]);

    config(['kinetik.kip.rk_tambahan_write' => true]);
    expect(app(PushPlanItemAction::class)->execute($item))->toBeTrue();

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/skp/rk') && $r['skpid'] === '1460709' && $r['jenis'] === 2 && $r['rencanakinerja'] === 'Menyiapkan bahan rapat koordinasi tambahan' && $r['isselesai'] === 0);
    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/kegiatan') && $r['rkid'] === '15000001');
    expect($item->fresh())->kip_rk_id->toBe('15000001')->and($rk->fresh()->kip_external_id)->toBe('15000001');
});
