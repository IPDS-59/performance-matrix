<?php

use App\Actions\Kinetik\BuildDataIntegrityAction;
use App\Actions\Kinetik\PushPlanItemAction;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use App\Services\Kinetik\MemberKipToken;
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

it('sends the checked kegiatan that are only in Kinetik to kipApp in one go, and says which did not go', function () {
    $user = User::factory()->create(['email' => 'anggota@bps.go.id']);
    $me = Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => '777']);
    $team = Team::factory()->create();
    $me->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'RK Inovasi', 'kip_external_id' => null]);
    $past = PlanItem::create(['team_id' => $team->id, 'employee_id' => $me->id, 'performance_plan_id' => $rk->id, 'description' => 'Kegiatan minggu lalu yang sudah selesai', 'date_start' => '2026-10-01', 'date_end' => '2026-10-02']);
    $future = PlanItem::create(['team_id' => $team->id, 'employee_id' => $me->id, 'performance_plan_id' => $rk->id, 'description' => 'Kegiatan minggu depan yang belum mulai', 'date_start' => '2026-10-20', 'date_end' => '2026-10-21']);

    $spy = new class(app(MemberKipToken::class)) extends PushPlanItemAction
    {
        public function execute(PlanItem $plan, bool $force = false): bool
        {
            $plan->forceFill(['kip_external_id' => '9990001', 'status' => 'pushed'])->save();

            return true;
        }
    };
    $this->app->instance(PushPlanItemAction::class, $spy);

    $this->actingAs($user)->post(route('plan-items.push-many'), ['ids' => [$past->id, $future->id]])
        ->assertSessionHas('error', fn ($m) => str_contains($m, '1 kegiatan terkirim') && str_contains($m, 'belum bisa dikirim sebelum tanggal mulainya'));
    expect($past->fresh()->kip_external_id)->toBe('9990001')->and($future->fresh()->kip_external_id)->toBeNull();
});

it('lets an admin review double kegiatan and ask each person once per request, remembering who asked', function () {
    $user = User::factory()->create();
    $me = Employee::factory()->create(['user_id' => $user->id, 'name' => 'Pegawai Uji']);
    $team = Team::factory()->create();
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'description' => 'RK Inovasi']);
    $a = PlanItem::create(['team_id' => $team->id, 'employee_id' => $me->id, 'performance_plan_id' => $rk->id, 'description' => 'Memperbaiki aplikasi kinetik', 'date_start' => '2026-10-08', 'date_end' => '2026-10-08', 'kip_external_id' => '5001', 'status' => 'pushed']);
    $b = PlanItem::create(['team_id' => $team->id, 'employee_id' => $me->id, 'performance_plan_id' => $rk->id, 'description' => 'Memperbaiki aplikasi Kinetik', 'date_start' => '2026-10-08', 'date_end' => '2026-10-08']);

    $this->actingAs($user)->get(route('admin.duplicate-kegiatan'))->assertForbidden();

    $admin = adminUser();
    $this->actingAs($admin)->get(route('admin.duplicate-kegiatan'))->assertOk()->assertInertia(fn ($p) => $p->component('Admin/DuplicateKegiatan')
        ->has('pairs', 1)->where('pairs.0.keep.id', $a->id)->where('pairs.0.drop.id', $b->id)->where('pairs.0.asked', null));

    $key = $a->id.'-'.$b->id;
    $this->actingAs($admin)->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('duplicate_pending', 1));
    $this->actingAs($admin)->post(route('admin.duplicate-kegiatan.ask'), ['keys' => [$key]])->assertSessionHas('success');
    expect($user->notifications()->count())->toBe(1)->and($user->notifications()->first()->data['pairs'])->toBe([$key]);

    $this->actingAs($admin)->get(route('admin.duplicate-kegiatan'))->assertInertia(fn ($p) => $p->where('pairs.0.asked.by', $admin->name)->where('duplicate_pending', 0));
    $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('duplicate_pending', 0));
    $this->actingAs($user)->post(route('admin.duplicate-kegiatan.ask'), ['keys' => [$key]])->assertForbidden();
});
