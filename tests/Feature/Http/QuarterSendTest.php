<?php

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
    Carbon::setTestNow('2026-11-08 09:00:00');
    config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'kinetik.kip.tahun' => 2026, 'kinetik.kip.send_to_atasan' => true, 'kinetik.kip.base_url' => 'https://kipapp.bps.go.id/api']);
});
afterEach(fn () => Carbon::setTestNow());

function quarterMember(): array
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => '777']);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    MemberKipCredential::remember($user, 'sso-pass', 'member-token', now()->addHours(5));
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'RK uji']);

    return [$user, $employee, $team, $rk];
}

function quarterItem(Employee $e, Team $t, PerformancePlan $rk, string $date, array $over = []): PlanItem
{
    return PlanItem::create([
        'team_id' => $t->id, 'employee_id' => $e->id, 'performance_plan_id' => $rk->id, 'description' => 'Kegiatan '.$date, 'date_start' => $date, 'date_end' => $date,
        'status' => 'done', 'progress' => 100, 'source' => 'kipapp', 'kip_external_id' => 'k'.rand(1000, 9999), ...$over,
    ]);
}

it('lists the kegiatan of the chosen quarter with what can be sent', function () {
    [$user, $e, $t, $rk] = quarterMember();
    quarterItem($e, $t, $rk, '2026-10-05');
    quarterItem($e, $t, $rk, '2026-12-20');
    quarterItem($e, $t, $rk, '2026-07-01');                                  // another quarter
    quarterItem($e, $t, $rk, '2026-10-06', ['kip_sent_at' => '2026-10-07']);
    quarterItem($e, $t, $rk, '2026-10-08', ['kip_external_id' => null, 'status' => 'planned']);   // not in kipApp yet
    MemberSkpStatus::remember($e->id, 2026, 4, 1, 'Sedang dibuat');

    $this->actingAs($user)->get(route('quarter-send.index'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Kinetik/QuarterSend')->where('quarter', 4)->where('skpStatus', 'Sedang dibuat')->has('rows', 4)
        ->where('rows', fn ($rows) => collect($rows)->where('can_send', true)->count() === 3 && collect($rows)->where('sent', true)->count() === 1));
});

it('sends a whole quarter in batches and takes it back', function () {
    [$user, $e, $t, $rk] = quarterMember();
    $items = collect(range(1, 3))->map(fn ($i) => quarterItem($e, $t, $rk, '2026-10-0'.$i, ['kip_external_id' => 'k'.$i]));
    MemberSkpStatus::remember($e->id, 2026, 4, 1, 'Sedang dibuat');
    Http::fake(['kipapp.bps.go.id/*' => Http::response(['status' => true], 200)]);

    $this->actingAs($user)->post(route('plan-items.send-many'), ['ids' => $items->pluck('id')->all()])->assertSessionHas('success');
    expect(PlanItem::whereIn('id', $items->pluck('id'))->whereNotNull('kip_sent_at')->count())->toBe(3);

    $this->actingAs($user)->post(route('plan-items.unsend-many'), ['ids' => $items->pluck('id')->all()])->assertSessionHas('success');
    expect(PlanItem::whereIn('id', $items->pluck('id'))->whereNull('kip_sent_at')->count())->toBe(3);
});

it('refuses a head or an account with no employee record', function () {
    $this->actingAs(User::factory()->create())->get(route('quarter-send.index'))->assertForbidden();
});

it('gives kipApp at most 100 kegiatan per call', function () {
    [$user, $e, $t, $rk] = quarterMember();
    $ids = collect(range(1, 205))->map(fn ($i) => quarterItem($e, $t, $rk, '2026-10-05', ['kip_external_id' => 'b'.$i])->id);
    MemberSkpStatus::remember($e->id, 2026, 4, 1, 'Sedang dibuat');
    Http::fake(['kipapp.bps.go.id/*' => Http::response(['status' => true], 200)]);

    $this->actingAs($user)->post(route('plan-items.send-many'), ['ids' => $ids->all()])->assertSessionHas('success');

    Http::assertSentCount(3);
    expect(PlanItem::whereNotNull('kip_sent_at')->count())->toBe(205);
});
