<?php

use App\Actions\Kinetik\BuildDuplicateKegiatanAction;
use App\Models\DuplicateKegiatanDismissal;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use App\Services\Kinetik\KegiatanDuplicateFinder;

beforeEach(fn () => seedRolesAndPermissions());

it('takes two texts as the same kegiatan only when every differing word is a typo or a filler word', function (string $a, string $b, bool $same) {
    expect((new KegiatanDuplicateFinder)->isSameKegiatan($a, $b))->toBe($same);
})->with([
    'case and spacing' => ['Memperbaiki aplikasi kinetik', 'memperbaiki  Aplikasi Kinetik', true],
    'typos' => ['Melakukan Transaksi keuangan berbasis aplikasi BOS', 'Melakukan Transaksi keuangan berbasi aplikasis BOS', true],
    'filler word' => ['Rapat persiapan Ukom', 'Rapat persiapan kegiatan Ukom', true],
    'another regency' => ['Pemeriksaan dokumen usulan sarana BPS Kabupaten Tolitoli Tahun 2028', 'Pemeriksaan dokumen usulan sarana BPS Kabupaten Sigi Tahun 2028', false],
    'another verb' => ['Monitoring Kegiatan SIMOPEL sebanyak 18 Pelabuhan', 'Pemeriksaan Kegiatan SIMOPEL sebanyak 18 Pelabuhan', false],
    'surat keluar or masuk' => ['Registrasi Surat Keluar di Aplikasi Srikandi', 'Registrasi Surat Masuk di Aplikasi Srikandi', false],
    'short code' => ['Perekaman SPP pembayaran LS pada Aplikasi SAKTI', 'Perekaman SPM pembayaran LS pada Aplikasi SAKTI', false],
    'another number' => ['Mengikuti BRS 2', 'Mengikuti BRS 5', false],
]);

/** @return array{0: Employee, 1: Team, 2: PerformancePlan} */
function duplicateOwner(): array
{
    $user = User::factory()->create();
    $me = Employee::factory()->create(['user_id' => $user->id, 'name' => 'Pegawai Uji']);
    $team = Team::factory()->create();
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'RK Umum']);

    return [$me, $team, $rk];
}

function sameDayKegiatan(Employee $me, Team $team, PerformancePlan $rk, string $description): PlanItem
{
    return PlanItem::create(['team_id' => $team->id, 'employee_id' => $me->id, 'performance_plan_id' => $rk->id, 'description' => $description, 'date_start' => now()->toDateString(), 'date_end' => now()->toDateString()]);
}

it('lists no pair for two kegiatan that only look alike', function () {
    [$me, $team, $rk] = duplicateOwner();
    sameDayKegiatan($me, $team, $rk, 'Pemeriksaan dokumen usulan sarana BPS Kabupaten Tolitoli');
    sameDayKegiatan($me, $team, $rk, 'Pemeriksaan dokumen usulan sarana BPS Kabupaten Sigi');

    expect((new BuildDuplicateKegiatanAction)->execute())->toBe([]);
});

it('lets an admin mark a pair as not double, so it leaves the list and the badge for good', function () {
    [$me, $team, $rk] = duplicateOwner();
    $a = sameDayKegiatan($me, $team, $rk, 'Rapat koordinasi tim umum');
    $b = sameDayKegiatan($me, $team, $rk, 'Rapat koordinasi tim umum');
    $key = $a->id.'-'.$b->id;
    $admin = adminUser();

    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('duplicate_pending', 1));
    $this->actingAs($admin)->post(route('admin.duplicate-kegiatan.dismiss'), ['keys' => [$key]])
        ->assertSessionHas('success', '1 pasangan ditandai bukan ganda.');

    $this->actingAs($admin)->get(route('admin.duplicate-kegiatan'))->assertInertia(fn ($p) => $p->has('pairs', 0));
    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('duplicate_pending', 0));
    $this->actingAs($admin)->post(route('admin.duplicate-kegiatan.dismiss'), ['keys' => [$key]])->assertSessionHas('success');
    expect(DuplicateKegiatanDismissal::count())->toBe(1);
});

it('lets only an admin mark a pair as not double', function () {
    [$me, $team, $rk] = duplicateOwner();
    $a = sameDayKegiatan($me, $team, $rk, 'Rapat koordinasi tim umum');
    $b = sameDayKegiatan($me, $team, $rk, 'Rapat koordinasi tim umum');

    $this->actingAs(User::factory()->create())->post(route('admin.duplicate-kegiatan.dismiss'), ['keys' => [$a->id.'-'.$b->id]])->assertForbidden();
});
