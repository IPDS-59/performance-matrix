<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;

it('redirects guests to login for rk index', function () {
    $this->get(route('performance-plans.index'))->assertRedirect(route('login'));
});

it('renders rk index for admin', function () {
    $this->withoutVite()->actingAs(adminUser())
        ->get(route('performance-plans.index'))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Index')->has('plans')->has('projects'));
});

it('filters rk by project_id', function () {
    $projectA = Project::factory()->create();
    $projectB = Project::factory()->create();
    PerformancePlan::factory()->create(['project_id' => $projectA->id]);
    PerformancePlan::factory()->create(['project_id' => $projectB->id]);

    $this->withoutVite()->actingAs(adminUser())
        ->get(route('performance-plans.index', ['project_id' => $projectA->id]))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Index')->has('plans', 1));
});

it('renders rk create form for admin', function () {
    $this->withoutVite()->actingAs(adminUser())
        ->get(route('performance-plans.create'))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Create')->has('projects')->has('employees'));
});

it('admin can store rk', function () {
    $project = Project::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('performance-plans.store'), [
            'project_id' => $project->id,
            'description' => 'Penyusunan Publikasi',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect(PerformancePlan::where('description', 'Penyusunan Publikasi')->exists())->toBeTrue();
});

it('validates required fields on rk store', function () {
    $this->actingAs(adminUser())
        ->post(route('performance-plans.store'), [])
        ->assertSessionHasErrors(['project_id', 'description', 'period_type']);
});

it('team lead can store rk for a project in their led team', function () {
    $user = staffUser();
    $team = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $project = Project::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('performance-plans.store'), [
            'project_id' => $project->id,
            'description' => 'RK Tim Lead',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect(PerformancePlan::where('description', 'RK Tim Lead')->exists())->toBeTrue();
});

it('team lead cannot store rk for a project outside their led teams', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $projectB = Project::factory()->create(['team_id' => $teamB->id]);

    $this->actingAs($user)
        ->post(route('performance-plans.store'), [
            'project_id' => $projectB->id,
            'description' => 'RK Tidak Diizinkan',
            'period_type' => 'year',
        ])
        ->assertSessionHasErrors(['project_id']);
});

it('admin can update rk', function () {
    $plan = PerformancePlan::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('performance-plans.update', $plan), [
            'project_id' => $plan->project_id,
            'description' => 'Updated RK',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect($plan->fresh()->description)->toBe('Updated RK');
});

it('team lead can update rk for their led teams project', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->put(route('performance-plans.update', $plan), [
            'description' => 'Updated By Lead',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect($plan->fresh()->description)->toBe('Updated By Lead');
});

it('team lead gets 403 when updating rk for another teams project', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $projectB = Project::factory()->create(['team_id' => $teamB->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $projectB->id]);

    $this->actingAs($user)
        ->put(route('performance-plans.update', $plan), [
            'description' => 'Unauthorized',
            'period_type' => 'year',
        ])
        ->assertForbidden();
});

it('admin can delete rk', function () {
    $plan = PerformancePlan::factory()->create();

    $this->actingAs(adminUser())
        ->delete(route('performance-plans.destroy', $plan))
        ->assertRedirect(route('performance-plans.index'));

    expect(PerformancePlan::find($plan->id))->toBeNull();
});

it('team lead can delete rk for their led teams project', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id]);

    $this->actingAs($user)
        ->delete(route('performance-plans.destroy', $plan))
        ->assertRedirect(route('performance-plans.index'));

    expect(PerformancePlan::find($plan->id))->toBeNull();
});

it('team lead gets 403 when deleting rk for another teams project', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $projectB = Project::factory()->create(['team_id' => $teamB->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $projectB->id]);

    $this->actingAs($user)
        ->delete(route('performance-plans.destroy', $plan))
        ->assertForbidden();
});

it('a plain member (no led teams, no owned plans) sees an empty index', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    // RKs in team that member belongs to but does not lead or own.
    PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    PerformancePlan::factory()->create(['project_id' => Project::factory()->create(['team_id' => $team->id])->id]);

    $this->withoutVite()->actingAs($user)
        ->get(route('performance-plans.index'))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Index')->has('plans', 0));
});

it('a member who owns a plan sees only their owned plan', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    $owned = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'pic_employee_id' => $employee->id]);
    // Another plan in the same team but not owned by this user.
    PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'pic_employee_id' => null]);

    $this->withoutVite()->actingAs($user)
        ->get(route('performance-plans.index'))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Index')->has('plans', 1));
});

it('team-scoped RK: leader can GET edit without 500', function () {
    $user = staffUser();
    $team = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);

    // Team-scoped plan: project_id = null, team_id set.
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    $this->withoutVite()->actingAs($user)
        ->get(route('performance-plans.edit', $plan))
        ->assertOk();
});

it('team-scoped RK: leader can PUT update', function () {
    $user = staffUser();
    $team = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);

    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    $this->actingAs($user)
        ->put(route('performance-plans.update', $plan), [
            'description' => 'Updated Team RK',
            'period_type' => 'year',
        ])
        ->assertRedirect(route('performance-plans.index'));

    expect($plan->fresh()->description)->toBe('Updated Team RK');
});

it('staff member with no plan and no led team sees empty index and gets 403 on edit', function () {
    $user = staffUser();
    Employee::factory()->create(['user_id' => $user->id]);

    $plan = PerformancePlan::factory()->create();

    $this->withoutVite()->actingAs($user)
        ->get(route('performance-plans.index'))
        ->assertInertia(fn ($page) => $page->component('PerformancePlans/Index')->has('plans', 0));

    $this->actingAs($user)
        ->get(route('performance-plans.edit', $plan))
        ->assertForbidden();
});

it('index includes can_update and can_delete flags; staff with no led team gets false', function () {
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['is_active' => true]);
    // User owns a plan but does not lead the team.
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'pic_employee_id' => $employee->id]);

    $this->withoutVite()->actingAs($user)
        ->get(route('performance-plans.index'))
        ->assertInertia(fn ($page) => $page
            ->component('PerformancePlans/Index')
            ->has('plans', 1)
            ->where('plans.0.can_update', false)
            ->where('plans.0.can_delete', false)
        );
});
