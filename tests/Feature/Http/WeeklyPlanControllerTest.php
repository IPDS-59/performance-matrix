<?php

use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Sources\MockKipActivitySource;
use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\KipActivity;
use App\Models\Team;
use App\Models\User;
use App\Models\WeeklyFocus;

function planTeam(): array
{
    $user = staffUser();
    $pj = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['leader_id' => $pj->id, 'kip_external_id' => '106453']);
    $pj->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $member = Employee::factory()->create(['display_name' => 'Andi']);
    $member->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    return [$user, $pj, $team, $member];
}

it('shows each member the RK without kegiatan, unfinished kegiatan and the PJ focus', function () {
    [$user, , $team, $member] = planTeam();
    foreach (['RK Inovasi' => 'rk-1', 'RK Metodologi' => 'rk-2'] as $name => $id) {
        EmployeeRk::create(['employee_id' => $member->id, 'kip_rk_id' => $id, 'name' => $name, 'team_kip_id' => '106453', 'year' => 2026]);
    }
    // Another team's RK is not listed.
    EmployeeRk::create(['employee_id' => $member->id, 'kip_rk_id' => 'rk-9', 'name' => 'RK Tim Lain', 'team_kip_id' => '999', 'year' => 2026]);
    KipActivity::factory()->create(['employee_id' => $member->id, 'rk_external_id' => 'rk-1', 'rk_name' => 'RK Inovasi', 'description' => 'Bangun fitur', 'progress' => 60, 'sent_at' => null, 'activity_date_start' => '2026-10-02', 'activity_date_end' => '2026-10-02']);
    // Last quarter's kegiatan does not count for Q4.
    KipActivity::factory()->create(['employee_id' => $member->id, 'rk_external_id' => 'rk-2', 'rk_name' => 'RK Metodologi', 'progress' => 100, 'activity_date_start' => '2026-09-10', 'activity_date_end' => '2026-09-10']);
    WeeklyFocus::create(['team_id' => $team->id, 'employee_id' => $member->id, 'week_start' => '2026-10-05', 'body' => 'Selesaikan fitur']);

    $this->actingAs($user)->get(route('weekly-plan.index', ['team' => $team->id, 'week' => '2026-10-07']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/WeeklyPlan')
            ->where('weekStart', '2026-10-05')
            ->where('quarter', 4)
            ->where('canManage', true)
            ->where('members', fn ($members) => collect($members)->firstWhere('name', 'Andi') == [
                'employee_id' => $member->id,
                'name' => 'Andi',
                'focus' => 'Selesaikan fitur',
                'rks_without_activity' => [['id' => EmployeeRk::where('kip_rk_id', 'rk-2')->value('id'), 'name' => 'RK Metodologi']],
                'unfinished' => [[
                    'id' => KipActivity::where('description', 'Bangun fitur')->value('id'),
                    'description' => 'Bangun fitur', 'date_start' => '2026-10-02', 'progress' => 60.0, 'rk_name' => 'RK Inovasi',
                    'evidence_url' => KipActivity::where('description', 'Bangun fitur')->value('evidence_url'),
                ]],
                'unsent_count' => 1,
                'activity_count' => 1,
                'rk_count' => 2,
            ]));
});

it('lets the PJ set and clear a member focus, and nobody else', function () {
    [$user, , $team, $member] = planTeam();
    $payload = ['team_id' => $team->id, 'employee_id' => $member->id, 'week_start' => '2026-10-07', 'body' => 'Entri SE2026'];

    $this->actingAs($user)->post(route('weekly-plan.focus'), $payload)->assertSessionHas('success');
    $this->actingAs($user)->post(route('weekly-plan.focus'), [...$payload, 'body' => 'Entri SE2026 blok 12'])->assertSessionHas('success');
    expect(WeeklyFocus::sole())->body->toBe('Entri SE2026 blok 12')
        ->and(WeeklyFocus::sole()->week_start)->toStartWith('2026-10-05');

    $this->actingAs($user)->post(route('weekly-plan.focus'), [...$payload, 'body' => ''])->assertSessionHas('success');
    expect(WeeklyFocus::count())->toBe(0);

    $outsider = Employee::factory()->create();
    $this->actingAs($user)->post(route('weekly-plan.focus'), [...$payload, 'employee_id' => $outsider->id])->assertStatus(422);

    $memberUser = User::factory()->create();
    $member->update(['user_id' => $memberUser->id]);
    $this->actingAs($memberUser)->post(route('weekly-plan.focus'), $payload)->assertForbidden();
});

it('stores each member RK list during the activity sync', function () {
    $employee = Employee::factory()->create(['nip_lama' => '340000001', 'kip_pegawai_id' => '777']);
    EmployeeRk::create(['employee_id' => $employee->id, 'kip_rk_id' => 'old', 'name' => 'RK lama', 'year' => now()->year]);

    app(SyncKipActivitiesAction::class)->execute(new MockKipActivitySource, collect([$employee]));

    expect(EmployeeRk::where('employee_id', $employee->id)->pluck('name')->all())->toBe(['RK Contoh'])
        ->and(EmployeeRk::first())->leader_rk->toBe('RK Ketua Contoh')->team_kip_id->toBe('1');
});
