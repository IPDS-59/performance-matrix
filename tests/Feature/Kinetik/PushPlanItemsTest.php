<?php

use App\Actions\Kinetik\CompletePlanItemAction;
use App\Actions\Kinetik\PushDuePlansAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Actions\Kinetik\ReconcilePlanItemsAction;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\MemberKipCredential;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRolesAndPermissions();
    Carbon::setTestNow('2026-10-08 06:00:00');
    config([
        'kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'kinetik.kip.tahun' => 2026,
        'kinetik.kip.periode_id' => 8,
    ]);
});
afterEach(fn () => Carbon::setTestNow());

function pushSetup(array $planAttrs = [], bool $withToken = true): PlanItem
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => '777']);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Inovasi', 'kip_external_id' => null]);
    if ($withToken) {
        MemberKipCredential::remember($user, 'sso-pass', 'member-token', now()->addHours(5));
    }

    return PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $rk->id,
        'description' => 'Entri SE2026', 'date_start' => '2026-10-08', 'date_end' => '2026-10-09', ...$planAttrs,
    ]);
}

function fakeKip(?array $kegiatanSequence = null): void
{
    Http::fake([
        '*/v1/skp/rk*' => Http::response([['rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi']]),
        '*/v1/skp*' => Http::response([['id' => '1460709', 'periodeid' => 8, 'periodepenilaianid' => 4], ['id' => '1', 'periodeid' => 3, 'periodepenilaianid' => 4]]),
        '*/v1/kegiatan*' => Http::sequence($kegiatanSequence ?? [
            Http::response([]),
            Http::response(['status' => true, 'message' => 'Successfully create/edit data']),
            Http::response([['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri SE2026', 'rkid' => '14656590']]),
        ]),
    ]);
}

it('pushes a plan on its start date with the member token and links the new kegiatan', function () {
    $plan = pushSetup();
    fakeKip();

    expect(app(PushDuePlansAction::class)->execute())->toBe(['pushed' => 1, 'failed' => 0]);

    expect($plan->fresh())->status->toBe('pushed')->kip_external_id->toBe('5550001')->kip_skp_id->toBe('1460709')->kip_rk_id->toBe('14656590')->push_error->toBeNull();
    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/kegiatan')
        && $r->hasHeader('x-auth', 'Bearer member-token')
        && $r['skpid'] === '1460709' && $r['rkid'] === '14656590' && $r['progres'] === 0 && $r['tanggal'] === '2026-10-08' && $r['tanggalselesai'] === '2026-10-09');
});

it('does not push plans of the future or of the past', function () {
    pushSetup(['date_start' => '2026-10-12', 'date_end' => '2026-10-13']);
    pushSetup(['date_start' => '2026-10-01', 'date_end' => '2026-10-02']);
    Http::fake();

    expect(app(PushDuePlansAction::class)->execute())->toBe(['pushed' => 0, 'failed' => 0]);
    Http::assertNothingSent();
});

it('stores the reason when the quarter SKP does not exist, tells the member once and retries next run', function () {
    $plan = pushSetup();
    Http::fake(['*/v1/skp*' => Http::response([])]);

    expect(app(PushDuePlansAction::class)->execute())->toBe(['pushed' => 0, 'failed' => 1]);
    app(PushDuePlansAction::class)->execute();

    $fresh = $plan->fresh();
    expect($fresh)->status->toBe('planned')->push_attempts->toBe(2)->push_error->toContain('SKP triwulan 4 belum dibuat')
        ->and($plan->employee->user->notifications->where('data.type', 'plan_push_failed'))->toHaveCount(1);
});

it('stops after one rejected SSO login and never sends the password again (T4)', function () {
    $plan = pushSetup();
    $user = $plan->employee->user;
    $user->memberKipCredential->update(['token_expires_at' => now()->subHour()]); // expired: needs a login
    Http::fake([
        '*/login' => Http::response('<form action="https://sso.bps.go.id/x">', 200),
        'sso.bps.go.id/*' => Http::response('<form action="x">Invalid username or password.', 200),
    ]);

    app(PushDuePlansAction::class)->execute();
    $loginsAfterFirst = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'sso.bps.go.id'))->count();
    app(PushDuePlansAction::class)->execute();
    $loginsAfterSecond = collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), 'sso.bps.go.id'))->count();

    expect($user->fresh()->memberKipCredential->login_failed_at)->not->toBeNull()
        ->and($loginsAfterFirst)->toBe(1)
        ->and($loginsAfterSecond)->toBe(1)
        ->and($user->fresh()->needsKipConnection())->toBeTrue()
        ->and($user->notifications->where('data.type', 'kip_password_failed'))->toHaveCount(1);
});

it('marks a plan complete in kipApp with progres 100, capaian and evidence', function () {
    $plan = pushSetup(['status' => 'pushed', 'kip_external_id' => '5550001', 'kip_skp_id' => '1460709', 'kip_rk_id' => '14656590']);
    Http::fake([
        '*/v1/kegiatan*' => Http::sequence([
            Http::response([['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri SE2026', 'rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi', 'tanggal' => '2026-10-08', 'tanggalselesai' => '2026-10-09', 'jammulai' => '08:00', 'jamselesai' => '16:00', 'progres' => 0]]),
            Http::response(['status' => true, 'message' => 'Successfully create/edit data']),
        ]),
    ]);

    app(CompletePlanItemAction::class)->execute($plan->load('employee.user', 'performancePlan'), '12 blok selesai', 'https://drive.example/bukti');

    expect($plan->fresh()->status)->toBe('done');
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['id'] === '5550001' && $r['progres'] === 100 && $r['capaian'] === '12 blok selesai' && $r['datadukung'] === 'https://drive.example/bukti' && $r['rkid'] === '14656590');
});

it('follows the kipApp progres after a sync, kipApp wins', function () {
    $plan = pushSetup(['status' => 'done', 'kip_external_id' => '5550001']);
    KipActivity::factory()->create(['employee_id' => $plan->employee_id, 'external_id' => '5550001', 'progress' => 40]);

    app(ReconcilePlanItemsAction::class)->execute([$plan->employee_id]);

    expect($plan->fresh())->status->toBe('in_progress')->kip_activity_id->not->toBeNull()->kip_synced_at->not->toBeNull();
});

it('lets a member push and complete through the endpoints, and blocks other members', function () {
    $plan = pushSetup();
    fakeKip();

    $this->actingAs($plan->employee->user)->post(route('plan-items.push', $plan))->assertSessionHas('success');
    expect($plan->fresh()->status)->toBe('pushed');

    $other = Employee::factory()->create(['user_id' => User::factory()->create()->id]);
    $other->teams()->attach($plan->team_id, ['role' => 'member', 'is_primary' => true]);
    $this->actingAs($other->user)->post(route('plan-items.complete', $plan), [])->assertForbidden();
});

it('sends uraian, bukti, progres and clock time in the kegiatan it creates', function () {
    $plan = pushSetup(['capaian_note' => 'Sudah 5 blok', 'evidence_url' => 'https://drive.example/b', 'progress' => 40, 'time_start' => '09:00:00', 'time_end' => '11:30:00', 'date_end' => '2026-10-08']);
    fakeKip();

    expect(app(PushPlanItemAction::class)->execute($plan->load('employee.user', 'performancePlan')))->toBeTrue();

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'v1/kegiatan') && $r['capaian'] === 'Sudah 5 blok' && $r['datadukung'] === 'https://drive.example/b' && $r['progres'] === 40 && $r['jammulai'] === '09:00' && $r['jamselesai'] === '11:30');
});

it('keeps the old kegiatan payload when the new fields are empty', function () {
    $plan = pushSetup();
    fakeKip();

    app(PushPlanItemAction::class)->execute($plan->load('employee.user', 'performancePlan'));

    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), 'v1/kegiatan') && $r['capaian'] === '' && $r['datadukung'] === '' && $r['progres'] === 0 && $r['jammulai'] === '08:00' && $r['jamselesai'] === '16:00');
});

it('stores the numeric capaian when a plan is completed', function () {
    $plan = pushSetup(['status' => 'pushed', 'kip_external_id' => '5550001', 'kip_skp_id' => '1460709', 'kip_rk_id' => '14656590']);
    Http::fake([
        '*/v1/kegiatan*' => Http::sequence([
            Http::response([['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri SE2026', 'rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi', 'tanggal' => '2026-10-08', 'tanggalselesai' => '2026-10-09']]),
            Http::response(['status' => true]),
        ]),
    ]);

    app(CompletePlanItemAction::class)->execute($plan->load('employee.user', 'performancePlan'), 'Selesai semua', 'https://d.example/x', 12.0);

    expect($plan->fresh())->capaian->toBe('12.00')->capaian_note->toBe('Selesai semua')->progress->toBe(100)->evidence_url->toBe('https://d.example/x');
});
