<?php

use App\Actions\Kinetik\BuildDataIntegrityAction;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    seedRolesAndPermissions();
    Carbon::setTestNow('2026-10-08 06:00:00');
});
afterEach(fn () => Carbon::setTestNow());

it('saves a kegiatan under an RK of a Pusat team that only the member owns', function () {
    $user = User::factory()->create();
    $me = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $me->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $pusat = PerformancePlan::factory()->create(['team_id' => null, 'project_id' => null, 'owner_employee_id' => $me->id, 'is_additional' => false, 'external_team_name' => 'Asesmen dan Konseling', 'external_project_name' => 'Projek Pusat', 'description' => 'Terlaksananya asesmen dan konseling pegawai yang baik']);

    $option = collect($this->actingAs($user)->get(route('weekly.index', ['week' => '2026-10-08']))->viewData('page')['props']['rkOptions'])->firstWhere('id', $pusat->id);

    $this->actingAs($user)->post(route('plan-items.store'), [
        'performance_plan_id' => $pusat->id, 'team_id' => $option['team_id'], 'description' => 'Menyusun instrumen asesmen',
        'date_start' => '2026-10-08', 'date_end' => '2026-10-08', 'target' => 1, 'target_unit' => 'dokumen',
    ])->assertSessionHasNoErrors();

    expect(PlanItem::where('performance_plan_id', $pusat->id)->count())->toBe(1);
});

it('saves a kegiatan once when the same save arrives twice, and shows the pair in the integrity check when two exist', function () {
    $user = User::factory()->create();
    $me = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $me->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'RK Inovasi']);
    $body = ['performance_plan_id' => $rk->id, 'team_id' => $team->id, 'description' => 'Memperbaiki aplikasi kinetik', 'date_start' => '2026-10-08', 'date_end' => '2026-10-08', 'target' => 1, 'target_unit' => 'web'];

    $this->actingAs($user)->post(route('plan-items.store'), $body)->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('plan-items.store'), $body)->assertSessionHasNoErrors();
    expect(PlanItem::where('employee_id', $me->id)->count())->toBe(1);

    PlanItem::create(['team_id' => $team->id, 'employee_id' => $me->id, 'performance_plan_id' => $rk->id, 'description' => 'Memperbaiki aplikasi Kinetik', 'date_start' => '2026-10-08', 'date_end' => '2026-10-08']);
    $check = collect(app(BuildDataIntegrityAction::class)->execute())->firstWhere('key', 'duplicate_kegiatan');
    expect($check['count'])->toBe(1);
});
