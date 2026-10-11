<?php

use App\Actions\Kinetik\ReconcilePlanItemsAction;
use App\Actions\Kinetik\SendPlanItemAction;
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
    config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32))]);
});
afterEach(fn () => Carbon::setTestNow());

function sendSetup(array $attrs = []): PlanItem
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => '777']);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'kip_external_id' => null]);
    MemberKipCredential::remember($user, 'sso-pass', 'member-token', now()->addHours(5));

    return PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $rk->id,
        'description' => 'Entri SE2026', 'date_start' => '2026-10-08', 'date_end' => '2026-10-09',
        'status' => 'pushed', 'kip_external_id' => '5550001', 'kip_skp_id' => '1460709', ...$attrs,
    ]);
}

it('sends the kegiatan to the atasan with the member token and remembers when', function () {
    $plan = sendSetup();
    Http::fake(['*/v1/kegiatan/kirim' => Http::response(['status' => true])]);

    app(SendPlanItemAction::class)->send($plan);

    expect($plan->fresh()->kip_sent_at)->not->toBeNull();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/kegiatan/kirim') && $r->hasHeader('x-auth', 'Bearer member-token') && $r['id'] === ['5550001']);
});

it('cancels a sent kegiatan', function () {
    $plan = sendSetup(['kip_sent_at' => now()]);
    Http::fake(['*/v1/kegiatan/batal' => Http::response(['status' => true])]);

    app(SendPlanItemAction::class)->cancel($plan);

    expect($plan->fresh()->kip_sent_at)->toBeNull();
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/kegiatan/batal') && $r['id'] === ['5550001']);
});

it('refuses to send a plan that is not in kipApp yet, or sent twice', function () {
    Http::fake();

    expect(fn () => app(SendPlanItemAction::class)->send(sendSetup(['status' => 'planned', 'kip_external_id' => null])))
        ->toThrow(RuntimeException::class, 'belum ada di kipApp');
    expect(fn () => app(SendPlanItemAction::class)->send(sendSetup(['kip_sent_at' => now()])))
        ->toThrow(RuntimeException::class, 'sudah dikirim');
    expect(fn () => app(SendPlanItemAction::class)->cancel(sendSetup()))
        ->toThrow(RuntimeException::class, 'belum dikirim');
    Http::assertNothingSent();
});

it('keeps the plan unsent and shows the reason when kipApp refuses', function () {
    $plan = sendSetup();
    Http::fake(['*/v1/kegiatan/kirim' => Http::response(['status' => false, 'message' => 'Capaian belum diisi'])]);

    expect(fn () => app(SendPlanItemAction::class)->send($plan))->toThrow(RuntimeException::class, 'Capaian belum diisi');
    expect($plan->fresh()->kip_sent_at)->toBeNull();
});

it('lets the member send through the page and shows an error message when kipApp refuses', function () {
    $plan = sendSetup();
    $user = $plan->employee->user;
    Http::fake(['*/v1/kegiatan/kirim' => Http::response(['status' => true])]);

    $this->actingAs($user)->post(route('plan-items.send', $plan))->assertSessionHas('success');
    expect($plan->fresh()->kip_sent_at)->not->toBeNull();

    Http::fake(['*/v1/kegiatan/batal' => Http::response(['status' => false, 'message' => 'Sudah dinilai'])]);
    $this->actingAs($user)->post(route('plan-items.unsend', $plan))->assertSessionHas('error');
    expect($plan->fresh()->kip_sent_at)->not->toBeNull();
});

it('does not let another member send someone else\'s plan', function () {
    $plan = sendSetup();
    $other = Employee::factory()->create(['user_id' => User::factory()->create()->id]);
    $other->teams()->attach($plan->team_id, ['role' => 'member', 'is_primary' => false]);
    Http::fake();

    $this->actingAs($other->user)->post(route('plan-items.send', $plan))->assertForbidden();
    Http::assertNothingSent();
});

it('follows the sent state of the kipApp kegiatan after a sync', function () {
    $plan = sendSetup(['kip_sent_at' => null]);
    KipActivity::factory()->create([
        'employee_id' => $plan->employee_id, 'external_id' => '5550001', 'progress' => 100, 'sent_at' => '2026-10-09 10:00:00',
    ]);

    app(ReconcilePlanItemsAction::class)->execute([$plan->employee_id]);

    expect($plan->fresh())->status->toBe('done')->kip_sent_at->not->toBeNull();
});
