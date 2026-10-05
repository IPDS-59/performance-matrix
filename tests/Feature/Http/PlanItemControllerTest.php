<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;

function planSetup(): array
{
    $pjUser = staffUser();
    $pj = Employee::factory()->create(['user_id' => $pjUser->id, 'display_name' => 'Sukma']);
    $team = Team::factory()->create(['leader_id' => $pj->id]);
    $pj->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $memberUser = User::factory()->create();
    $member = Employee::factory()->create(['user_id' => $memberUser->id]);
    $member->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Inovasi']);

    return [$pjUser, $team, $member, $memberUser, $rk];
}

function planPayload(Team $team, PerformancePlan $rk, array $extra = []): array
{
    return ['team_id' => $team->id, 'performance_plan_id' => $rk->id, 'description' => 'Entri SE2026', 'date_start' => '2026-10-05', 'date_end' => '2026-10-07', ...$extra];
}

it('lets a member plan for themselves and the PJ plan for a member, with a notice', function () {
    [$pjUser, $team, $member, $memberUser, $rk] = planSetup();

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk))->assertSessionHas('success');
    expect(PlanItem::sole())->employee_id->toBe($member->id)->source->toBe('member')->status->toBe('planned');

    $this->actingAs($pjUser)->post(route('plan-items.store'), planPayload($team, $rk, ['employee_id' => $member->id, 'description' => 'Rapat ISO']))->assertSessionHas('success');
    expect(PlanItem::where('description', 'Rapat ISO')->first())->source->toBe('pj')
        ->and($memberUser->notifications)->toHaveCount(1)
        ->and($memberUser->notifications->first()->data['message'])->toContain('Sukma ditambahkan rencana untuk Anda');
});

it('blocks members from planning for others, and rejects RK of another team', function () {
    [, $team, $member, $memberUser, $rk] = planSetup();
    $other = Employee::factory()->create();
    $other->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, ['employee_id' => $other->id]))->assertForbidden();

    $foreignRk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => Team::factory()->create()->id]);
    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $foreignRk))->assertSessionHasErrors('performance_plan_id');
    expect(PlanItem::count())->toBe(0);
});

it('requires a Projek when the team has projects and the RK has none', function () {
    [, $team, , $memberUser, $rk] = planSetup();
    $project = Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'RK Ketua']);
    $rk->update(['leader_rk' => 'RK Ketua']);
    Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'RK Ketua']);

    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk))->assertSessionHasErrors('project_id');
    $this->actingAs($memberUser)->post(route('plan-items.store'), planPayload($team, $rk, ['project_id' => $project->id]))->assertSessionHas('success');
    expect(PlanItem::sole()->project_id)->toBe($project->id);
});

it('edits and cancels only plans still in the planned state', function () {
    [$pjUser, $team, $member, $memberUser, $rk] = planSetup();
    $item = PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => 'Awal', 'date_start' => '2026-10-05', 'date_end' => '2026-10-05', 'created_by' => $member->id]);

    $this->actingAs($memberUser)->patch(route('plan-items.update', $item), planPayload($team, $rk, ['description' => 'Diubah']))->assertSessionHas('success');
    expect($item->fresh()->description)->toBe('Diubah');

    $this->actingAs($memberUser)->patch(route('plan-items.update', $item), planPayload($team, $rk, ['date_end' => '2026-10-01']))->assertSessionHasErrors('date_end');

    $this->actingAs($pjUser)->delete(route('plan-items.destroy', $item))->assertSessionHas('success');
    expect($item->fresh()->status)->toBe('cancelled');

    $item->update(['status' => 'pushed']);
    $this->actingAs($memberUser)->patch(route('plan-items.update', $item), planPayload($team, $rk))->assertStatus(422);
});

it('shows the week plans and RK options on the board, leaving out cancelled ones', function () {
    [$pjUser, $team, $member, , $rk] = planSetup();
    foreach ([['Minggu ini', '2026-10-06', '2026-10-06', 'planned'], ['Minggu lalu', '2026-09-29', '2026-09-30', 'planned'], ['Batal', '2026-10-06', '2026-10-06', 'cancelled']] as [$d, $s, $e, $st]) {
        PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => $d, 'date_start' => $s, 'date_end' => $e, 'status' => $st]);
    }

    $this->actingAs($pjUser)->get(route('weekly-plan.index', ['team' => $team->id, 'week' => '2026-10-05']))
        ->assertInertia(fn ($page) => $page
            ->where('members', fn ($m) => collect($m)->firstWhere('employee_id', $member->id)['plans'][0]['description'] === 'Minggu ini'
                && count(collect($m)->firstWhere('employee_id', $member->id)['plans']) === 1)
            ->where('rkOptions.0.description', 'RK Inovasi'));
});
