<?php

use App\Actions\Kinetik\CompletePlanItemAction;
use App\Actions\Kinetik\FindMissingQuarterSkpsAction;
use App\Actions\Kinetik\PushDuePlansAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Actions\Kinetik\ReconcilePlanItemsAction;
use App\Kinetik\PlanStatus;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\MemberKipCredential;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

it('stores the reason when the quarter SKP cannot be created, tells the member once and retries next run', function () {
    $plan = pushSetup();
    Http::fake(['*/v1/skp*' => Http::response([])]);

    expect(app(PushDuePlansAction::class)->execute())->toBe(['pushed' => 0, 'failed' => 1]);
    app(PushDuePlansAction::class)->execute();

    $fresh = $plan->fresh();
    expect($fresh)->status->toBe('planned')->push_attempts->toBe(2)->push_error->toContain('SKP Tahunan belum ada')
        ->and($plan->employee->user->notifications->where('data.type', 'plan_push_failed'))->toHaveCount(1);
});

function fakeKipWithoutQuarterSkp(int $yearlyStatus = 6): array
{
    $created = false;
    $kegiatanPosted = false;
    $posted = [];
    Http::fake(function ($request) use (&$created, &$kegiatanPosted, &$posted, $yearlyStatus) {
        $url = $request->url();
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $q);
        if (str_contains($url, '/v1/skp/bulanan')) {
            $created = true;
            $posted = $request->data();

            return Http::response(['status' => true]);
        }
        if (str_contains($url, '/v1/skp/rk/copy/bulanan')) {
            return Http::response(['rk' => [
                ['id' => '900', 'rencanakinerja' => 'RK Inovasi', 'flagrk' => 1, 'iscopied' => false, 'iki' => [['id' => '9001'], ['id' => '9002']]],
                ['id' => '901', 'rencanakinerja' => 'RK Lain', 'flagrk' => 1, 'iscopied' => false, 'iki' => [['id' => '9011']]],
            ]]);
        }
        if (str_contains($url, '/v1/skp/rk')) {
            return Http::response([['rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi']]);
        }
        if (str_contains($url, '/v1/skp')) {
            if (($q['jenis'] ?? null) === '1') {
                return Http::response([['id' => '100', 'periodeid' => 8, 'statusskpid' => $yearlyStatus, 'statusskp' => $yearlyStatus === 6 ? 'Ditetapkan' : 'Sedang dibuat', 'unitkerjaid' => '100', 'wilayahid' => '7200_11']]);
            }
            $rows = [['id' => '300', 'periodeid' => 8, 'periodepenilaianid' => 3]];
            if ($created) {
                $rows[] = ['id' => '1460709', 'periodeid' => 8, 'periodepenilaianid' => 4];
            }

            return Http::response($rows);
        }
        if (str_contains($url, '/v1/kegiatan')) {
            if ($request->method() === 'POST') {
                $kegiatanPosted = true;

                return Http::response(['status' => true]);
            }

            return Http::response($kegiatanPosted ? [['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri SE2026', 'rkid' => '14656590']] : []);
        }

        return Http::response([], 404);
    });

    return [function () use (&$posted) {
        return $posted;
    }];
}

it('creates the quarter SKP from the yearly one when the quarter has none, then pushes the plan', function () {
    $plan = pushSetup();
    [$posted] = fakeKipWithoutQuarterSkp();

    expect(app(PushDuePlansAction::class)->execute())->toBe(['pushed' => 1, 'failed' => 0]);

    expect($plan->fresh())->status->toBe('pushed')->kip_skp_id->toBe('1460709');
    // Only the RK the member also had last quarter (RK Inovasi), with its IKI.
    expect($posted())->toMatchArray(['id' => '100', 'periodepenilaianid' => 4, 'flagmethod' => 1])
        ->and($posted()['data'])->toBe([['rk' => '900', 'iki' => ['9001', '9002'], 'flagrk' => 1]]);
});

it('adds the plan RK to a quarter SKP that does not have it yet, keeping the RK it has', function () {
    $plan = pushSetup();
    $added = false;
    $kegiatanPosted = false;
    $body = [];
    Http::fake(function ($request) use (&$added, &$kegiatanPosted, &$body) {
        $url = $request->url();
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $q);
        if (str_contains($url, '/v1/skp/bulanan')) {
            $added = true;
            $body = $request->data();

            return Http::response(['status' => true]);
        }
        if (str_contains($url, '/v1/skp/rk/copy/bulanan')) {
            return Http::response(['rk' => [
                ['id' => '800', 'rencanakinerja' => 'RK Lain', 'flagrk' => 1, 'iscopied' => true, 'iki' => [['id' => '8001']]],
                ['id' => '900', 'rencanakinerja' => 'RK Inovasi', 'flagrk' => 1, 'iscopied' => false, 'iki' => [['id' => '9001']]],
            ]]);
        }
        if (str_contains($url, '/v1/skp/rk')) {
            return Http::response($added
                ? [['rkid' => '1', 'rencanakinerja' => 'RK Lain'], ['rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi']]
                : [['rkid' => '1', 'rencanakinerja' => 'RK Lain']]);
        }
        if (str_contains($url, '/v1/skp')) {
            return ($q['jenis'] ?? null) === '1'
                ? Http::response([['id' => '100', 'periodeid' => 8, 'statusskpid' => 6, 'unitkerjaid' => '100', 'wilayahid' => '7200_11']])
                : Http::response([['id' => '1460709', 'periodeid' => 8, 'periodepenilaianid' => 4]]);
        }
        if (str_contains($url, '/v1/kegiatan')) {
            if ($request->method() === 'POST') {
                $kegiatanPosted = true;

                return Http::response(['status' => true]);
            }

            return Http::response($kegiatanPosted ? [['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri SE2026', 'rkid' => '14656590']] : []);
        }

        return Http::response([], 404);
    });

    expect(app(PushDuePlansAction::class)->execute())->toBe(['pushed' => 1, 'failed' => 0]);

    expect($plan->fresh())->status->toBe('pushed')->kip_rk_id->toBe('14656590');
    expect($body)->toMatchArray(['id' => '1460709', 'periodepenilaianid' => 4, 'flagmethod' => 2])
        ->and(collect($body['data'])->pluck('rk')->all())->toBe(['800', '900']);
});

it('does not create the quarter SKP while the yearly SKP is not Ditetapkan', function () {
    $plan = pushSetup();
    [$posted] = fakeKipWithoutQuarterSkp(yearlyStatus: 5);

    expect(app(PushDuePlansAction::class)->execute())->toBe(['pushed' => 0, 'failed' => 1]);

    expect($plan->fresh())->status->toBe('planned')->push_error->toContain('SKP Tahunan belum Ditetapkan')
        ->and($posted())->toBe([]);
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

it('finds the quarters that have no SKP in kipApp yet', function () {
    config(['kinetik.kip.source' => 'api']);
    $plan = pushSetup(['date_start' => '2026-12-14', 'date_end' => '2026-12-15']);   // a TW 4 plan not sent yet
    Http::fake(['*/v1/skp*' => Http::response([
        ['id' => '11', 'periodeid' => 8, 'periodepenilaianid' => 3],
        ['id' => '12', 'periodeid' => 8, 'periodepenilaianid' => 1],
    ])]);

    $missing = app(FindMissingQuarterSkpsAction::class)->execute($plan->employee->user);

    expect($missing)->toBe([4]);   // the current quarter (TW 4 on 8 Oct) and the plan's both need it
});

it('does not push a plan twice while another push of it is still running', function () {
    $plan = pushSetup();
    fakeKip();
    $held = Cache::lock('plan-push:'.$plan->id, 60);
    $held->get();

    expect(app(PushPlanItemAction::class)->execute($plan))->toBeFalse();

    Http::assertNothingSent();
    expect($plan->fresh())->status->toBe('planned')->push_error->toBeNull()->push_attempts->toBe(0);
    $held->release();
});

it('does not create a second kegiatan when the plan was pushed by another request in the meantime', function () {
    $plan = pushSetup();
    $stale = PlanItem::find($plan->id);   // loaded before the other request linked the kegiatan
    $plan->update(['kip_external_id' => '5550001', 'status' => 'pushed']);
    fakeKip();

    expect(app(PushPlanItemAction::class)->execute($stale->load(['employee.user', 'performancePlan'])))->toBeTrue();

    Http::assertNothingSent();
});

it('answers a second Kirim ke kipApp with a message instead of an error page', function () {
    $plan = pushSetup(['kip_external_id' => '5550001', 'status' => 'pushed']);

    $this->actingAs($plan->employee->user)->post(route('plan-items.push', $plan))
        ->assertRedirect()->assertSessionHas('success', 'Kegiatan ini sudah ada di kipApp.');
});

it('tells the member a push is still running instead of a blank error', function () {
    $plan = pushSetup();
    fakeKip();
    $held = Cache::lock('plan-push:'.$plan->id, 60);
    $held->get();

    $this->actingAs($plan->employee->user)->post(route('plan-items.push', $plan))
        ->assertSessionHas('error', 'Kegiatan ini sedang dikirim ke kipApp. Muat ulang halaman sebentar lagi.');
    $held->release();
});

// Only a finished kegiatan goes to kipApp

it('lists what a kegiatan still lacks before it may go to kipApp', function () {
    config(['kinetik.kip.push_only_finished' => true]);
    $plan = pushSetup(['target' => 1, 'progress' => 40]);

    expect($plan->missingForKip())->toBe(['progres 100', 'capaian (angka)', 'uraian capaian (minimal 10 karakter)', 'bukti dukung']);

    $plan->forceFill(['progress' => 100, 'capaian' => 1, 'capaian_note' => 'Seluruh blok selesai', 'evidence_url' => 'https://bukti.test/a']);
    expect($plan->missingForKip())->toBe([]);
});

it('does not send an unfinished plan on its start date, and sends a finished one even after its dates passed', function () {
    config(['kinetik.kip.push_only_finished' => true]);
    fakeKip();
    $unfinished = pushSetup(['date_start' => '2026-10-08', 'date_end' => '2026-10-09']);
    $finishedLate = pushSetup(['date_start' => '2026-10-01', 'date_end' => '2026-10-03', 'progress' => 100, 'capaian_note' => 'Seluruh blok selesai', 'evidence_url' => 'https://bukti.test/a']);

    app(PushDuePlansAction::class)->execute();

    expect($unfinished->fresh()->kip_external_id)->toBeNull()->and($unfinished->fresh()->push_error)->toBeNull()
        ->and($finishedLate->fresh()->kip_external_id)->not->toBeNull()
        ->and($finishedLate->fresh()->status)->toBe('done');
});

it('tells the member what to fill when pressing Kirim ke kipApp on an unfinished plan', function () {
    config(['kinetik.kip.push_only_finished' => true]);
    Http::fake();
    $plan = pushSetup(['progress' => 100]);

    $this->actingAs($plan->employee->user)->post(route('plan-items.push', $plan))
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'Belum lengkap') && str_contains($m, 'uraian capaian') && str_contains($m, 'bukti dukung'));
    Http::assertNothingSent();
});

it('sends repeated finished kegiatan of a month as one when the saklar is on, adding target and capaian and widening the range', function () {
    config(['kinetik.kip.merge_repeated' => true, 'kinetik.kip.push_only_finished' => true]);
    $done = ['target' => 3, 'capaian' => 3, 'progress' => 100, 'capaian_note' => 'Monitoring selesai dilakukan', 'evidence_url' => 'https://example.com/b', 'description' => 'Monitoring persiapan lapangan'];
    $first = pushSetup($done + ['date_start' => '2026-10-01', 'date_end' => '2026-10-02']);
    $second = PlanItem::create(['team_id' => $first->team_id, 'employee_id' => $first->employee_id, 'performance_plan_id' => $first->performance_plan_id, 'date_start' => '2026-10-06', 'date_end' => '2026-10-07', ...$done, 'target' => 4, 'capaian' => 4]);
    $other = PlanItem::create(['team_id' => $first->team_id, 'employee_id' => $first->employee_id, 'performance_plan_id' => $first->performance_plan_id, 'date_start' => '2026-10-07', 'date_end' => '2026-10-07', ...$done, 'description' => 'Rapat koordinasi tim wilayah']);
    fakeKip([Http::response([]), Http::response(['status' => true, 'message' => 'Successfully create/edit data']), Http::response([['kegiatanperhariid' => 5550001, 'kegiatan' => 'Monitoring persiapan lapangan', 'rkid' => '14656590']])]);

    $pushed = app(PushPlanItemAction::class)->execute($first);
    expect($pushed)->toBeTrue($first->fresh()->push_error ?? 'no error');

    expect($first->fresh())->kip_external_id->toBe('5550001')
        ->and(substr((string) $first->fresh()->date_start, 0, 10))->toBe('2026-10-01')
        ->and(substr((string) $first->fresh()->date_end, 0, 10))->toBe('2026-10-07')
        ->and((float) $first->fresh()->target)->toBe(7.0)->and((float) $first->fresh()->capaian)->toBe(7.0);
    expect($second->fresh())->status->toBe('cancelled')->merged_into_id->toBe($first->id)->kip_external_id->toBeNull();
    // A different text of the same month stays a kegiatan of its own.
    expect($other->fresh())->status->not->toBe('cancelled')->merged_into_id->toBeNull();
    Http::assertSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/kegiatan') && $r['tanggal'] === '2026-10-01' && $r['tanggalselesai'] === '2026-10-07');
});

it('does not merge anything while the saklar is off', function () {
    config(['kinetik.kip.merge_repeated' => false, 'kinetik.kip.push_only_finished' => true]);
    $done = ['target' => 3, 'capaian' => 3, 'progress' => 100, 'capaian_note' => 'Monitoring selesai dilakukan', 'evidence_url' => 'https://example.com/b', 'description' => 'Monitoring persiapan lapangan'];
    $first = pushSetup($done + ['date_start' => '2026-10-01', 'date_end' => '2026-10-02']);
    $second = PlanItem::create(['team_id' => $first->team_id, 'employee_id' => $first->employee_id, 'performance_plan_id' => $first->performance_plan_id, 'date_start' => '2026-10-06', 'date_end' => '2026-10-07', ...$done]);
    fakeKip([Http::response([]), Http::response(['status' => true, 'message' => 'Successfully create/edit data']), Http::response([['kegiatanperhariid' => 5550001, 'kegiatan' => 'Monitoring persiapan lapangan', 'rkid' => '14656590']])]);

    app(PushPlanItemAction::class)->execute($first);

    expect($second->fresh())->status->not->toBe('cancelled')->and((float) $first->fresh()->target)->toBe(3.0);
});

it('updates kipApp too when the realisasi of a kegiatan already there is saved, and keeps Kinetik when kipApp refuses', function () {
    $plan = pushSetup(['kip_external_id' => '5550001', 'kip_skp_id' => '1460709', 'status' => 'pushed']);
    $row = ['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri SE2026', 'rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi', 'tanggal' => '2026-10-08', 'tanggalselesai' => '2026-10-09', 'progres' => 0, 'capaian' => '', 'datadukung' => ''];
    Http::fake(['*/v1/kegiatan*' => Http::sequence([Http::response([$row]), Http::response(['status' => true, 'message' => 'ok']), Http::response([$row]), Http::response(['status' => false, 'message' => 'Data dukung tidak valid'], 422)])]);
    $user = $plan->employee->user;

    $this->actingAs($user)->patch(route('plan-items.result', $plan), ['capaian' => 2, 'progress' => 100, 'capaian_note' => 'Entri selesai seluruhnya', 'evidence_url' => 'https://example.com/bukti'])
        ->assertSessionHas('success', 'Realisasi disimpan, kipApp ikut diperbarui.');
    Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/v1/kegiatan') && $r['datadukung'] === 'https://example.com/bukti' && $r['progres'] === 100 && $r['capaian'] === 'Entri selesai seluruhnya');
    expect($plan->fresh())->status->toBe('done')->evidence_url->toBe('https://example.com/bukti');
    expect($plan->fresh()->stage()->value)->toBe('done');

    // kipApp refuses the next save: Kinetik still has it, and the member is told kipApp is behind.
    $this->actingAs($user)->patch(route('plan-items.result', $plan), ['evidence_url' => 'https://example.com/lain'])->assertSessionHas('warning');
    expect($plan->fresh()->evidence_url)->toBe('https://example.com/lain');
});

it('keeps the plan status in step with the kipApp progres and treats cancelled as final', function () {
    expect(PlanStatus::fromKipProgress(0)->value)->toBe('pushed')
        ->and(PlanStatus::fromKipProgress(40)->value)->toBe('in_progress')
        ->and(PlanStatus::fromKipProgress(100)->value)->toBe('done')
        ->and(PlanStatus::Cancelled->canBecome(PlanStatus::Done))->toBeFalse()
        ->and(PlanStatus::Done->canBecome(PlanStatus::Planned))->toBeFalse()
        ->and(PlanStatus::Planned->canBecome(PlanStatus::Done))->toBeTrue();
});

it('sends a kegiatan to kipApp at once when a save makes it complete, and gives stuck kegiatan their tries back on a new login', function () {
    config(['kinetik.kip.push_only_finished' => true]);
    $plan = pushSetup(['target' => 2, 'target_unit' => 'dok']);
    fakeKip();
    $user = $plan->employee->user;

    // Not complete yet: stays in Kinetik.
    $this->actingAs($user)->patch(route('plan-items.result', $plan), ['capaian' => 2, 'progress' => 100])->assertSessionHas('success');
    expect($plan->fresh()->kip_external_id)->toBeNull();

    // Uraian and bukti dukung complete it: it goes to kipApp in the same save.
    $this->actingAs($user)->patch(route('plan-items.result', $plan), ['capaian' => 2, 'progress' => 100, 'capaian_note' => 'Entri selesai seluruhnya', 'evidence_url' => 'https://example.com/b'])
        ->assertSessionHas('success', 'Realisasi lengkap. Kegiatan langsung dikirim ke kipApp.');
    expect($plan->fresh())->kip_external_id->toBe('5550001')->status->toBe('done');

    $stuck = PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $plan->employee_id, 'performance_plan_id' => $plan->performance_plan_id, 'description' => 'Kegiatan yang gagal dikirim', 'date_start' => '2026-10-07', 'date_end' => '2026-10-07', 'push_attempts' => 5, 'push_error' => 'Token kipApp Anda kedaluwarsa']);
    MemberKipCredential::remember($user, 'sso-pass', 'new-token', now()->addHours(5));
    expect($stuck->fresh())->push_attempts->toBe(0)->push_error->toBeNull();
});
