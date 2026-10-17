<?php

use App\Actions\Kinetik\SyncKipActivitiesAction;
use App\Kinetik\Sources\MockKipActivitySource;
use App\Models\Employee;
use App\Models\EmployeeRk;
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
