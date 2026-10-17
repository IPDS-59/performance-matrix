<?php

use App\Actions\Kinetik\CompletePlanItemAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Actions\Kinetik\SendPlanItemAction;
use App\Models\Employee;
use App\Models\MemberKipCredential;
use App\Models\MemberSkpStatus;
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
        'kinetik.kip.send_to_atasan' => true,
    ]);
});
afterEach(fn () => Carbon::setTestNow());

function entryPlan(array $attrs = []): PlanItem
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => (string) fake()->unique()->numberBetween(1000, 999999), 'nip_lama' => (string) fake()->unique()->numerify('34########')]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Inovasi', 'kip_external_id' => null]);
    MemberKipCredential::remember($user, 'sso-pass', 'member-token', now()->addHours(5));

    return PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $rk->id,
        'description' => 'Entri data SE2026', 'date_start' => '2026-10-08', 'date_end' => '2026-10-08', ...$attrs,
    ]);
}

function entryInKip(array $attrs = []): PlanItem
{
    return entryPlan(['status' => 'pushed', 'kip_external_id' => '5550001', 'kip_skp_id' => '1460709', 'kip_rk_id' => '14656590', ...$attrs]);
}

function kipRow(array $over = []): array
{
    return [['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri data SE2026', 'rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi', 'tanggal' => '2026-10-08', 'tanggalselesai' => null, 'jammulai' => null, 'jamselesai' => null, 'progres' => 0, ...$over]];
}

function fakePush(?array $skp = null): void
{
    Http::fake([
        '*/v1/skp/rk*' => Http::response([['rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi']]),
        '*/v1/skp*' => Http::response([$skp ?? ['id' => '1460709', 'periodeid' => 8, 'periodepenilaianid' => 4, 'statusskpid' => 1, 'statusskp' => 'Sedang dibuat']]),
        '*/v1/kegiatan*' => Http::sequence([
            Http::response([]),
            Http::response(['status' => true]),
            Http::response([['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri data SE2026', 'rkid' => '14656590']]),
        ]),
    ]);
}

function pushedBody(): array
{
    return collect(Http::recorded())->map(fn ($p) => $p[0])->first(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v1/kegiatan'))->data();
}

// Item 2

it('keeps the 08:00 and 16:00 defaults and the end date while KIP_NULL_TIMES is off', function () {
    config(['kinetik.kip.null_times' => false]);
    fakePush();

    app(PushPlanItemAction::class)->execute(entryPlan()->load('employee.user', 'performancePlan'));

    expect(pushedBody())->toMatchArray(['tanggal' => '2026-10-08', 'tanggalselesai' => '2026-10-08', 'jammulai' => '08:00', 'jamselesai' => '16:00', 'iscapaianskp' => 1, 'parentid' => null]);
});

it('sends null end date and null times for a plain one-day kegiatan while KIP_NULL_TIMES is on', function () {
    config(['kinetik.kip.null_times' => true]);
    fakePush();

    app(PushPlanItemAction::class)->execute(entryPlan()->load('employee.user', 'performancePlan'));

    expect(pushedBody())->toMatchArray(['tanggal' => '2026-10-08', 'tanggalselesai' => null, 'jammulai' => null, 'jamselesai' => null]);
});

it('still sends the end date of a range and the times of a timed kegiatan with KIP_NULL_TIMES on', function () {
    config(['kinetik.kip.null_times' => true]);
    fakePush();

    app(PushPlanItemAction::class)->execute(entryPlan(['date_end' => '2026-10-09', 'time_start' => '09:00', 'time_end' => '11:30'])->load('employee.user', 'performancePlan'));

    expect(pushedBody())->toMatchArray(['tanggalselesai' => '2026-10-09', 'jammulai' => '09:00', 'jamselesai' => '11:30']);
});

it('passes the null times of the kipApp row back on complete only with KIP_NULL_TIMES on', function (bool $on, $end, $start) {
    config(['kinetik.kip.null_times' => $on]);
    $plan = entryInKip();
    Http::fake(['*/v1/kegiatan*' => Http::sequence([Http::response(kipRow()), Http::response(['status' => true])])]);

    app(CompletePlanItemAction::class)->execute($plan->load('employee.user', 'performancePlan'), 'Sudah selesai semua', null);

    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['tanggalselesai'] === $end && $r['jammulai'] === $start && $r['iscapaianskp'] === 1);
})->with([[false, '2026-10-08', '08:00'], [true, null, null]]);

// Item 6 and 9: capaian SKP and copy

it('sends iscapaianskp 0 and the kipApp id of the source kegiatan as parentid', function () {
    $source = entryInKip();
    fakePush();
    $copy = entryPlan(['employee_id' => $source->employee_id, 'team_id' => $source->team_id, 'performance_plan_id' => $source->performance_plan_id, 'is_capaian_skp' => false, 'parent_id' => $source->id]);

    app(PushPlanItemAction::class)->execute($copy->load('employee.user', 'performancePlan'));

    expect(pushedBody())->toMatchArray(['iscapaianskp' => 0, 'parentid' => '5550001']);
});

// Item 12

it('stores the SKP status when the quarter SKP is loaded, and an unknown status locks nothing', function () {
    fakePush(['id' => '1460709', 'periodeid' => 8, 'periodepenilaianid' => 4, 'statusskpid' => 5, 'statusskp' => 'Dikirim']);
    $plan = entryPlan();

    app(PushPlanItemAction::class)->execute($plan->load('employee.user', 'performancePlan'));

    expect(MemberSkpStatus::idFor($plan->employee_id, 2026, 4))->toBe(5);
    expect(MemberSkpStatus::idFor($plan->employee_id, 2026, 3))->toBeNull();
});

// Item 3, 4: validation

function entryPost(User $user, Team $team, PerformancePlan $rk, array $extra = [])
{
    return test()->actingAs($user)->post(route('plan-items.store'), ['team_id' => $team->id, 'performance_plan_id' => $rk->id, 'description' => 'Entri data SE2026', 'date_start' => '2026-10-05', 'date_end' => '2026-10-07', ...$extra]);
}

it('keeps both dates in one quarter of the SKP year', function () {
    $plan = entryPlan();
    $user = $plan->employee->user;
    $team = $plan->employee->teams->first();
    $rk = $plan->performancePlan;

    entryPost($user, $team, $rk, ['date_start' => '2026-09-29', 'date_end' => '2026-10-02'])->assertSessionHasErrors('date_end');
    entryPost($user, $team, $rk, ['date_start' => '2025-10-05', 'date_end' => '2025-10-06'])->assertSessionHasErrors('date_start');
    entryPost($user, $team, $rk, ['date_start' => '2026-10-29', 'date_end' => '2026-11-04'])->assertSessionHasNoErrors();
    // Future dates inside the quarter stay allowed for planning.
    entryPost($user, $team, $rk, ['date_start' => '2026-12-20', 'date_end' => '2026-12-21'])->assertSessionHasNoErrors();
});

it('asks for a kegiatan text of at least 10 characters that is not only spaces', function () {
    $plan = entryPlan();
    $args = [$plan->employee->user, $plan->employee->teams->first(), $plan->performancePlan];

    entryPost(...$args, extra: ['description' => '         '])->assertSessionHasErrors(['description' => 'Masukan Kegiatan. Kegiatan tidak boleh hanya spasi.']);
    entryPost(...$args, extra: ['description' => 'Pendek'])->assertSessionHasErrors(['description' => 'Kegiatan minimal 10 karakter.']);
    entryPost(...$args, extra: ['description' => 'Cukup panjang'])->assertSessionHasNoErrors();
});

it('needs a capaian text of 10 characters only when the kegiatan is marked complete', function () {
    $plan = entryInKip();
    $user = $plan->employee->user;
    Http::fake(['*/v1/kegiatan*' => Http::sequence([Http::response(kipRow()), Http::response(['status' => true])])]);

    $this->actingAs($user)->post(route('plan-items.complete', $plan), [])->assertSessionHasErrors(['capaian_note' => 'Masukan Capaian']);
    $this->actingAs($user)->post(route('plan-items.complete', $plan), ['capaian_note' => 'Pendek'])->assertSessionHasErrors(['capaian_note' => 'Capaian minimal 10 karakter.']);
    Http::assertNothingSent();

    $this->actingAs($user)->post(route('plan-items.complete', $plan), ['capaian_note' => 'Seluruh blok selesai'])->assertSessionHas('success');
    expect($plan->fresh()->status)->toBe('done');
    // A plan can be saved with a short or empty capaian while planning.
    $this->actingAs($user)->patch(route('plan-items.result', $plan), ['capaian_note' => 'ok'])->assertSessionHasNoErrors();
});

it('refuses to push a plan before its start date', function () {
    $plan = entryPlan(['date_start' => '2026-10-20', 'date_end' => '2026-10-21']);
    Http::fake();

    $this->actingAs($plan->employee->user)->post(route('plan-items.push', $plan))->assertSessionHas('error', 'Kegiatan belum bisa dikirim sebelum tanggal mulainya.');
    $this->actingAs($plan->employee->user)->post(route('plan-items.complete', $plan), ['capaian_note' => 'Seluruh blok selesai'])->assertSessionHas('error');
    expect($plan->fresh()->status)->toBe('planned');
    Http::assertNothingSent();
});

// Item 7: edit after push

function editPayload(PlanItem $plan, array $extra = []): array
{
    return ['performance_plan_id' => $plan->performance_plan_id, 'description' => 'Deskripsi sudah diubah', 'date_start' => '2026-10-08', 'date_end' => '2026-10-09', 'progress' => 50, 'capaian_note' => 'Separuh blok selesai', ...$extra];
}

it('refuses to edit a kegiatan already in kipApp while KIP_EDIT_AFTER_PUSH is off', function () {
    config(['kinetik.kip.edit_after_push' => false]);
    $plan = entryInKip();
    Http::fake();

    $this->actingAs($plan->employee->user)->patch(route('plan-items.update', $plan), editPayload($plan))->assertStatus(422);
    Http::assertNothingSent();
});

it('edits a pushed kegiatan in kipApp first and then in Kinetik', function () {
    config(['kinetik.kip.edit_after_push' => true, 'kinetik.kip.null_times' => true]);
    $plan = entryInKip();
    Http::fake(['*/v1/kegiatan*' => Http::sequence([Http::response(kipRow()), Http::response(['status' => true])])]);

    $this->actingAs($plan->employee->user)->patch(route('plan-items.update', $plan), editPayload($plan))->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['id'] === '5550001' && $r['kegiatan'] === 'Deskripsi sudah diubah' && $r['tanggalselesai'] === '2026-10-09' && $r['progres'] === 50 && $r['jammulai'] === null && $r['rkid'] === '14656590');
    expect($plan->fresh())->description->toBe('Deskripsi sudah diubah')->status->toBe('in_progress')->progress->toBe(50);
});

it('keeps Kinetik unchanged and shows the message when kipApp refuses an edit', function () {
    config(['kinetik.kip.edit_after_push' => true]);
    $plan = entryInKip();
    Http::fake(['*/v1/kegiatan*' => Http::sequence([Http::response(kipRow()), Http::response(['status' => false, 'message' => 'Kegiatan sudah dinilai'])])]);

    $this->actingAs($plan->employee->user)->patch(route('plan-items.update', $plan), editPayload($plan))->assertSessionHas('error');

    expect(session('error'))->toContain('Kegiatan sudah dinilai');
    expect($plan->fresh())->description->toBe('Entri data SE2026')->status->toBe('pushed');
});

it('keeps Kinetik unchanged when kipApp is down during an edit', function () {
    config(['kinetik.kip.edit_after_push' => true]);
    $plan = entryInKip();
    Http::fake(['*/v1/kegiatan*' => Http::response('boom', 500)]);

    $this->actingAs($plan->employee->user)->patch(route('plan-items.update', $plan), editPayload($plan))->assertSessionHas('error');
    expect($plan->fresh()->description)->toBe('Entri data SE2026');
});

it('lets a sent kegiatan change only "Masukan ke capaian SKP"', function () {
    config(['kinetik.kip.edit_after_push' => true]);
    $plan = entryInKip(['kip_sent_at' => now()]);
    Http::fake(['*/v1/kegiatan*' => Http::sequence([Http::response(kipRow()), Http::response(['status' => true])])]);

    $this->actingAs($plan->employee->user)->patch(route('plan-items.update', $plan), editPayload($plan, ['is_capaian_skp' => false]))->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['iscapaianskp'] === 0 && $r['kegiatan'] === 'Entri data SE2026');
    expect($plan->fresh())->is_capaian_skp->toBeFalse()->description->toBe('Entri data SE2026');
});

it('locks edit and delete when the quarter SKP left "Sedang dibuat" and "dikembalikan"', function () {
    config(['kinetik.kip.edit_after_push' => true]);
    $plan = entryInKip();
    Http::fake(['*/v1/kegiatan*' => Http::sequence([Http::response(kipRow()), Http::response(['status' => true])])]);

    MemberSkpStatus::remember($plan->employee_id, 2026, 4, 5, 'Dikirim');
    $this->actingAs($plan->employee->user)->patch(route('plan-items.update', $plan), editPayload($plan))->assertStatus(422);
    $this->actingAs($plan->employee->user)->delete(route('plan-items.destroy', $plan))->assertStatus(422);

    MemberSkpStatus::remember($plan->employee_id, 2026, 4, 6, 'Dikembalikan');
    $this->actingAs($plan->employee->user)->patch(route('plan-items.update', $plan), editPayload($plan))->assertSessionHas('success');
});

// Item 8: delete in kipApp

it('only cancels in Kinetik after push while KIP_EDIT_AFTER_PUSH is off (refused)', function () {
    config(['kinetik.kip.edit_after_push' => false]);
    $plan = entryInKip();
    Http::fake();

    $this->actingAs($plan->employee->user)->delete(route('plan-items.destroy', $plan))->assertStatus(422);
    Http::assertNothingSent();
    expect($plan->fresh()->status)->toBe('pushed');
});

it('deletes the kegiatan in kipApp and keeps the plan as cancelled', function () {
    config(['kinetik.kip.edit_after_push' => true]);
    $plan = entryInKip();
    Http::fake(['*/v1/kegiatan' => Http::response(['status' => true])]);

    $this->actingAs($plan->employee->user)->delete(route('plan-items.destroy', $plan))->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'DELETE' && $r['id'] === '5550001');
    expect($plan->fresh()->status)->toBe('cancelled');
});

it('keeps the plan when kipApp refuses the delete, and never deletes a sent kegiatan', function () {
    config(['kinetik.kip.edit_after_push' => true]);
    $plan = entryInKip();
    Http::fake(['*/v1/kegiatan' => Http::response(['status' => false, 'message' => 'Sudah dinilai'])]);

    $this->actingAs($plan->employee->user)->delete(route('plan-items.destroy', $plan))->assertSessionHas('error');
    expect($plan->fresh()->status)->toBe('pushed');

    $sent = entryInKip(['kip_sent_at' => now()]);
    $this->actingAs($sent->employee->user)->delete(route('plan-items.destroy', $sent))->assertStatus(422);
});

// Item 11: bulk send

it('sends several kegiatan in one kipApp call and cancels them in one call', function () {
    $a = entryInKip(['kip_external_id' => '5550001']);
    $b = entryPlan(['employee_id' => $a->employee_id, 'team_id' => $a->team_id, 'performance_plan_id' => $a->performance_plan_id, 'status' => 'done', 'kip_external_id' => '5550002', 'kip_skp_id' => '1460709']);
    Http::fake(['*/v1/kegiatan/*' => Http::response(['status' => true])]);

    $this->actingAs($a->employee->user)->post(route('plan-items.send-many'), ['ids' => [$a->id, $b->id]])->assertSessionHas('success');

    Http::assertSentCount(1);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/kegiatan/kirim') && $r['id'] === ['5550001', '5550002']);
    expect($a->fresh()->kip_sent_at)->not->toBeNull()->and($b->fresh()->kip_sent_at)->not->toBeNull();

    $this->actingAs($a->employee->user)->post(route('plan-items.unsend-many'), ['ids' => [$a->id, $b->id]])->assertSessionHas('success', 'Kegiatan berhasil dikembalikan.');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/kegiatan/batal') && $r['id'] === ['5550001', '5550002']);
    expect($a->fresh()->kip_sent_at)->toBeNull();
});

it('sends nothing when one selected kegiatan cannot be sent, or the SKP is locked', function () {
    $a = entryInKip();
    $planned = entryPlan(['employee_id' => $a->employee_id, 'team_id' => $a->team_id, 'performance_plan_id' => $a->performance_plan_id]);
    Http::fake();

    $this->actingAs($a->employee->user)->post(route('plan-items.send-many'), ['ids' => [$a->id, $planned->id]])->assertSessionHas('error');
    $this->actingAs($a->employee->user)->post(route('plan-items.send-many'), ['ids' => []])->assertSessionHasErrors('ids');

    MemberSkpStatus::remember($a->employee_id, 2026, 4, 5, 'Dikirim');
    $this->actingAs($a->employee->user)->post(route('plan-items.send-many'), ['ids' => [$a->id]])->assertSessionHas('error', 'SKP triwulan sudah dikirim untuk dinilai.');
    Http::assertNothingSent();
});

it('does not let a member send the kegiatan of someone else in bulk', function () {
    $mine = entryInKip();
    $theirs = entryInKip();
    Http::fake();

    $this->actingAs($mine->employee->user)->post(route('plan-items.send-many'), ['ids' => [$mine->id, $theirs->id]])->assertForbidden();
    Http::assertNothingSent();
});

it('shows kipApp\'s message and changes nothing when the bulk send is refused', function () {
    $a = entryInKip();
    Http::fake(['*/v1/kegiatan/kirim' => Http::response(['status' => false, 'message' => 'Capaian belum diisi'])]);

    $this->actingAs($a->employee->user)->post(route('plan-items.send-many'), ['ids' => [$a->id]])->assertSessionHas('error');
    expect(session('error'))->toContain('Capaian belum diisi');
    expect($a->fresh()->kip_sent_at)->toBeNull();
});

it('keeps single send working through the list action', function () {
    $a = entryInKip();
    Http::fake(['*/v1/kegiatan/kirim' => Http::response(['status' => true])]);

    app(SendPlanItemAction::class)->send($a->load('employee.user'));
    expect($a->fresh()->kip_sent_at)->not->toBeNull();
});

// Item 10: presensi

it('lists the presensi of the quarter of the date, sorted by date', function () {
    $plan = entryPlan();
    Http::fake(['*/v1/kegiatan/presensi*' => Http::response([
        ['id' => 2, 'fullDate' => '2026-10-06', 'time' => '2026-10-06 08:00', 'description' => 'Rapat koordinasi'],
        ['id' => 1, 'fullDate' => '2026-10-02', 'description' => 'Entri data'],
        ['id' => 3, 'fullDate' => null, 'description' => 'Tanpa tanggal'],
        ['id' => 4, 'tanggal' => '2026-10-07', 'description' => '  '],
    ])]);

    $response = $this->actingAs($plan->employee->user)->getJson(route('plan-items.presensi', ['date' => '2026-10-05']));

    $response->assertOk()->assertJsonPath('data.0.date', '2026-10-02')->assertJsonPath('data.1.description', 'Rapat koordinasi')->assertJsonCount(2, 'data');
    Http::assertSent(fn ($r) => $r->method() === 'GET' && str_contains($r->url(), 'periodeid=8&periodepenilaianid=4&niplama='.$plan->employee->nip_lama) && $r->hasHeader('x-auth', 'Bearer member-token'));
});

it('answers with a message instead of crashing when presensi cannot be read', function () {
    $plan = entryPlan();
    Http::fake(['*/v1/kegiatan/presensi*' => Http::response('boom', 500)]);

    $this->actingAs($plan->employee->user)->getJson(route('plan-items.presensi', ['date' => '2026-10-05']))->assertStatus(422)->assertJsonStructure(['message']);
    $this->actingAs($plan->employee->user)->getJson(route('plan-items.presensi'))->assertStatus(422);

    $plan->employee->update(['nip_lama' => null]);
    $this->actingAs($plan->employee->user->unsetRelation('employee'))->getJson(route('plan-items.presensi', ['date' => '2026-10-05']))->assertStatus(422)->assertJsonPath('message', 'NIP lama pegawai belum terisi, jadi presensi tidak dapat diambil.');
});

// Item 9: copy through the form

it('stores the copy source and capaian SKP choice of a new kegiatan', function () {
    $source = entryPlan();
    $user = $source->employee->user;
    $team = $source->employee->teams->first();

    entryPost($user, $team, $source->performancePlan, ['parent_id' => $source->id, 'is_capaian_skp' => false])->assertSessionHasNoErrors();
    expect(PlanItem::latest('id')->first())->parent_id->toBe($source->id)->is_capaian_skp->toBeFalse();

    $stranger = entryPlan();
    entryPost($user, $team, $source->performancePlan, ['parent_id' => $stranger->id])->assertSessionHasErrors('parent_id');
});

it('tells the weekly page what each kegiatan allows', function () {
    config(['kinetik.kip.edit_after_push' => true]);
    $plan = entryInKip();
    MemberSkpStatus::remember($plan->employee_id, 2026, 4, 5, 'Dikirim');

    $this->actingAs($plan->employee->user)->get(route('weekly.index', ['week' => '2026-10-08']))->assertInertia(fn ($page) => $page
        ->where('thisWeek.0.can_edit', false)->where('thisWeek.0.can_send', false)
        ->where('thisWeek.0.lock_reason', 'SKP triwulan sudah dikirim untuk dinilai.')->where('thisWeek.0.startable', true));
});
