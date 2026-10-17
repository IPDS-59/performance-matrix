<?php

use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyFocus;

function weekSetup(): array
{
    $user = User::factory()->create();
    $me = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['leader_id' => $me->id]);
    $me->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $colleague = Employee::factory()->create();
    $colleague->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Inovasi']);

    return [$user, $me, $colleague, $team, $rk];
}

function weekItem(Employee $e, Team $t, PerformancePlan $rk, string $desc, string $start, string $end, array $extra = []): PlanItem
{
    return PlanItem::create(['team_id' => $t->id, 'employee_id' => $e->id, 'performance_plan_id' => $rk->id, 'description' => $desc, 'date_start' => $start, 'date_end' => $end, ...$extra]);
}

it('requires login', function () {
    $this->get(route('weekly.index'))->assertRedirect(route('login'));
});

it('shows only the signed-in member, last week and this week apart, a PJ too', function () {
    [$user, $me, $colleague, $team, $rk] = weekSetup();
    weekItem($me, $team, $rk, 'Minggu ini saya', '2026-10-13', '2026-10-14', ['target' => 5, 'target_unit' => 'blok']);
    weekItem($me, $team, $rk, 'Minggu lalu saya', '2026-10-06', '2026-10-07', ['capaian' => 3.5, 'target' => 4, 'progress' => 100, 'status' => 'done']);
    weekItem($me, $team, $rk, 'Batal', '2026-10-13', '2026-10-13', ['status' => 'cancelled']);
    weekItem($colleague, $team, $rk, 'Milik rekan', '2026-10-13', '2026-10-14');
    WeeklyFocus::create(['team_id' => $team->id, 'employee_id' => $me->id, 'week_start' => '2026-10-12', 'body' => 'Fokus minggu ini']);

    $this->actingAs($user)->get(route('weekly.index', ['week' => '2026-10-15']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/RekapMingguan')
            ->where('weekStart', '2026-10-12')
            ->where('lastWeekStart', '2026-10-05')
            ->where('focus', 'Fokus minggu ini')
            ->where('canSendToAtasan', false)
            ->has('thisWeek', 1)
            ->where('thisWeek.0.description', 'Minggu ini saya')
            ->where('thisWeek.0.target', 5)
            ->has('lastWeek', 1)
            ->where('lastWeek.0.description', 'Minggu lalu saya')
            ->where('lastWeek.0.capaian', 3.5)
            ->where('rkOptions.0.description', 'RK Inovasi')
            ->where('rkOptions.0.team_id', $team->id));
});

it('offers only the member own RK once they are synced', function () {
    [$user, $me, , $team, $rk] = weekSetup();
    PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Orang Lain']);

    $this->actingAs($user)->get(route('weekly.index', ['week' => '2026-10-15']))->assertInertia(fn ($p) => $p->has('rkOptions', 2)->where('rkSynced', false));

    EmployeeRk::create(['employee_id' => $me->id, 'kip_rk_id' => 'r1', 'name' => 'rk  inovasi', 'year' => now()->year]);
    $this->actingAs($user)->get(route('weekly.index', ['week' => '2026-10-15']))->assertInertia(fn ($p) => $p->has('rkOptions', 1)->where('rkOptions.0.description', 'RK Inovasi')->where('rkSynced', true));
});

it('sends the old plan page to the new one and keeps the week', function () {
    $this->actingAs(User::factory()->create())->get('/rencana-minggu?week=2026-10-05&team=3')
        ->assertRedirect(route('weekly.index', ['week' => '2026-10-05']));
});

it('works for an account without an employee record', function () {
    $this->actingAs(User::factory()->create())->get(route('weekly.index'))
        ->assertOk()->assertInertia(fn ($p) => $p->where('employee', null)->missing('thisWeek'));
});

it('walks the member from the months to the weeks of a month to one week, with only their own counts', function () {
    [$user, $me, $colleague, $team, $rk] = weekSetup();
    weekItem($me, $team, $rk, 'Punya saya satu', '2026-10-13', '2026-10-14', ['capaian' => 2]);
    weekItem($me, $team, $rk, 'Punya saya dua', '2026-10-20', '2026-10-21');
    weekItem($colleague, $team, $rk, 'Punya rekan', '2026-10-13', '2026-10-14');

    $this->actingAs($user)->get(route('weekly.index', ['year' => 2026]))->assertInertia(fn ($p) => $p
        ->where('view', 'months')->where('periods.9.activities', 2)->where('periods.9.written', 1)->where('periods.0.activities', 0));

    $this->actingAs($user)->get(route('weekly.index', ['year' => 2026, 'month' => 10]))->assertInertia(fn ($p) => $p
        ->where('view', 'weeks')->where('periods', fn ($weeks) => collect($weeks)->firstWhere('key', '2026-10-12')['activities'] === 1
            && collect($weeks)->firstWhere('key', '2026-10-19')['activities'] === 1));

    $this->actingAs($user)->get(route('weekly.index', ['week' => '2026-10-13']))->assertInertia(fn ($p) => $p->where('view', 'detail')->where('periods', []));
});
