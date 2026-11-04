<?php

use App\Models\Employee;
use App\Models\MemberKipCredential;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRolesAndPermissions();
    Carbon::setTestNow('2026-11-04 08:00:00');
    config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'kinetik.kip.tahun' => 2026, 'kinetik.kip.periode_id' => 8]);
});
afterEach(fn () => Carbon::setTestNow());

function realisasiItem(array $attrs = []): PlanItem
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => '777']);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Inovasi']);
    MemberKipCredential::remember($user, 'sso-pass', 'member-token', now()->addHours(5));

    return PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $rk->id,
        'description' => 'Entri data SE2026', 'date_start' => '2026-10-08', 'date_end' => '2026-10-08', ...$attrs,
    ]);
}

it('lists last month by default and only my own kegiatan', function () {
    $mine = realisasiItem();
    realisasiItem(['description' => 'Kegiatan orang lain di bulan yang sama']);
    realisasiItem(['description' => 'Kegiatan bulan lain', 'date_start' => '2026-09-08', 'date_end' => '2026-09-08']);

    $this->actingAs($mine->employee->user)->get(route('realisasi.monthly'))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->where('month', '2026-10')->has('items', 1)->where('items.0.id', $mine->id));
});

it('saves target and capaian of many kegiatan in Kinetik without touching kipApp', function () {
    $item = realisasiItem();
    Http::fake();

    $this->actingAs($item->employee->user)->post(route('realisasi.monthly.store'), ['rows' => [
        ['id' => $item->id, 'target' => 4, 'target_unit' => 'dokumen', 'capaian' => 3, 'capaian_note' => 'Tiga dokumen selesai diperiksa', 'progress' => 75],
    ]])->assertSessionHas('success');

    Http::assertNothingSent();
    expect($item->fresh())->target->toEqual(4.0)->target_unit->toBe('dokumen')->capaian->toEqual(3.0)->progress->toBe(75);
});

it('leaves another member\'s kegiatan alone', function () {
    $mine = realisasiItem();
    $other = realisasiItem(['description' => 'Kegiatan milik orang lain']);

    $this->actingAs($mine->employee->user)->post(route('realisasi.monthly.store'), ['rows' => [['id' => $other->id, 'target' => 9]]]);

    expect($other->fresh()->target)->toBeNull();
});

it('marks a kegiatan done in kipApp in the same save, and reports what kipApp could not take', function () {
    $item = realisasiItem(['status' => 'pushed', 'kip_external_id' => '5550001', 'kip_skp_id' => '1460709', 'kip_rk_id' => '14656590']);
    $short = realisasiItem(['description' => 'Kegiatan tanpa uraian', 'status' => 'pushed', 'kip_external_id' => '5550002', 'kip_skp_id' => '1460709']);
    Http::fake(['*/v1/kegiatan*' => Http::sequence([
        Http::response([['kegiatanperhariid' => 5550001, 'kegiatan' => 'Entri data SE2026', 'rkid' => '14656590', 'rencanakinerja' => 'RK Inovasi', 'tanggal' => '2026-10-08', 'tanggalselesai' => null, 'jammulai' => null, 'jamselesai' => null, 'progres' => 0]]),
        Http::response(['status' => true]),
    ])]);

    $this->actingAs($item->employee->user)->post(route('realisasi.monthly.store'), ['rows' => [
        ['id' => $item->id, 'capaian' => 1, 'capaian_note' => 'Entri data selesai seluruhnya', 'to_kip' => true],
    ]])->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r->method() === 'PUT' && $r['progres'] === 100 && $r['capaian'] === 'Entri data selesai seluruhnya');
    expect($item->fresh()->status)->toBe('done');

    $this->actingAs($short->employee->user)->post(route('realisasi.monthly.store'), ['rows' => [['id' => $short->id, 'capaian' => 1, 'capaian_note' => 'pendek', 'to_kip' => true]]])
        ->assertSessionHas('warning');
    expect($short->fresh()->capaian)->toEqual(1.0);
});
