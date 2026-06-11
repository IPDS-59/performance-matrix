<?php

use App\Models\Employee;
use App\Models\PerformanceIndicator;
use App\Models\Team;

it('redirects guests to login for iku index', function () {
    $this->get(route('performance-indicators.index'))->assertRedirect(route('login'));
});

it('renders iku index for admin', function () {
    $this->withoutVite()->actingAs(adminUser())
        ->get(route('performance-indicators.index'))
        ->assertInertia(fn ($page) => $page->component('PerformanceIndicators/Index')->has('indicators')->has('teams'));
});

it('renders iku index for staff', function () {
    $this->withoutVite()->actingAs(staffUser())
        ->get(route('performance-indicators.index'))
        ->assertInertia(fn ($page) => $page->component('PerformanceIndicators/Index'));
});

it('filters iku by year and team', function () {
    $team = Team::factory()->create();
    PerformanceIndicator::factory()->create(['team_id' => $team->id, 'year' => 2025]);
    PerformanceIndicator::factory()->create(['team_id' => $team->id, 'year' => 2026]);

    $this->withoutVite()->actingAs(adminUser())
        ->get(route('performance-indicators.index', ['year' => 2025, 'team_id' => $team->id]))
        ->assertInertia(fn ($page) => $page->component('PerformanceIndicators/Index')->has('indicators', 1));
});

it('renders iku create form for admin', function () {
    $this->withoutVite()->actingAs(adminUser())
        ->get(route('performance-indicators.create'))
        ->assertInertia(fn ($page) => $page->component('PerformanceIndicators/Create')->has('teams'));
});

it('denies iku create for staff with no led team', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    Employee::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('performance-indicators.create'))
        ->assertForbidden();
});

it('admin can store iku', function () {
    $team = Team::factory()->create();

    $this->actingAs(adminUser())
        ->post(route('performance-indicators.store'), [
            'team_id' => $team->id,
            'year' => 2026,
            'name' => 'Jumlah Publikasi',
            'target' => 5,
            'target_unit' => 'Dokumen',
        ])
        ->assertRedirect(route('performance-indicators.index'));

    expect(PerformanceIndicator::where('name', 'Jumlah Publikasi')->exists())->toBeTrue();
});

it('validates required fields on iku store', function () {
    $this->actingAs(adminUser())
        ->post(route('performance-indicators.store'), [])
        ->assertSessionHasErrors(['team_id', 'year', 'name']);
});

it('team lead can store iku for a led team', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
    $teamB->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->post(route('performance-indicators.store'), [
            'team_id' => $teamB->id,
            'year' => 2026,
            'name' => 'IKU Tim B',
        ])
        ->assertRedirect(route('performance-indicators.index'));

    expect(PerformanceIndicator::where('name', 'IKU Tim B')->exists())->toBeTrue();
});

it('team lead cannot store iku for a team they do not lead', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $teamC = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $teamA->id]);
    $teamB->update(['leader_id' => $employee->id]);

    $this->actingAs($user)
        ->post(route('performance-indicators.store'), [
            'team_id' => $teamC->id,
            'year' => 2026,
            'name' => 'IKU Tidak Diizinkan',
        ])
        ->assertSessionHasErrors(['team_id']);
});

it('admin can update iku', function () {
    $indicator = PerformanceIndicator::factory()->create();

    $this->actingAs(adminUser())
        ->put(route('performance-indicators.update', $indicator), [
            'team_id' => $indicator->team_id,
            'year' => $indicator->year,
            'name' => 'Updated IKU Name',
        ])
        ->assertRedirect(route('performance-indicators.index'));

    expect($indicator->fresh()->name)->toBe('Updated IKU Name');
});

it('team lead can update iku for their led team', function () {
    $user = staffUser();
    $team = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->put(route('performance-indicators.update', $indicator), [
            'year' => $indicator->year,
            'name' => 'Updated By Lead',
        ])
        ->assertRedirect(route('performance-indicators.index'));

    expect($indicator->fresh()->name)->toBe('Updated By Lead');
});

it('team lead gets 403 when updating iku for another team', function () {
    $user = staffUser();
    $teamA = Team::factory()->create(['is_active' => true]);
    $teamB = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $teamB->id]);

    $this->actingAs($user)
        ->put(route('performance-indicators.update', $indicator), [
            'year' => $indicator->year,
            'name' => 'Unauthorized Update',
        ])
        ->assertForbidden();
});

it('admin can delete iku', function () {
    $indicator = PerformanceIndicator::factory()->create();

    $this->actingAs(adminUser())
        ->delete(route('performance-indicators.destroy', $indicator))
        ->assertRedirect(route('performance-indicators.index'));

    expect(PerformanceIndicator::find($indicator->id))->toBeNull();
});

it('team lead can delete iku for their led team', function () {
    $user = staffUser();
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team->update(['leader_id' => $employee->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->delete(route('performance-indicators.destroy', $indicator))
        ->assertRedirect(route('performance-indicators.index'));

    expect(PerformanceIndicator::find($indicator->id))->toBeNull();
});

it('team lead gets 403 when deleting iku for another team', function () {
    $user = staffUser();
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $teamA->update(['leader_id' => $employee->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $teamB->id]);

    $this->actingAs($user)
        ->delete(route('performance-indicators.destroy', $indicator))
        ->assertForbidden();
});

it('staff member gets 403 on iku edit', function () {
    $user = staffUser();
    $team = Team::factory()->create(['is_active' => true]);
    Employee::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->get(route('performance-indicators.edit', $indicator))
        ->assertForbidden();
});

it('iku index includes can_update and can_delete = false for staff with no led team', function () {
    $user = staffUser();
    $team = Team::factory()->create(['is_active' => true]);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'team_id' => $team->id]);
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $indicator = PerformanceIndicator::factory()->create(['team_id' => $team->id, 'year' => now()->year]);

    $this->withoutVite()->actingAs($user)
        ->get(route('performance-indicators.index'))
        ->assertInertia(fn ($page) => $page
            ->component('PerformanceIndicators/Index')
            ->where('indicators.0.can_update', false)
            ->where('indicators.0.can_delete', false)
        );
});

it('iku index includes can_update and can_delete = true for admin', function () {
    $team = Team::factory()->create(['is_active' => true]);
    PerformanceIndicator::factory()->create(['team_id' => $team->id, 'year' => now()->year]);

    $this->withoutVite()->actingAs(adminUser())
        ->get(route('performance-indicators.index'))
        ->assertInertia(fn ($page) => $page
            ->component('PerformanceIndicators/Index')
            ->where('indicators.0.can_update', true)
            ->where('indicators.0.can_delete', true)
        );
});
