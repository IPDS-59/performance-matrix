<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\RecapOverride;
use App\Models\Team;
use App\Models\TeamRecapEvidence;
use App\Models\User;

/**
 * Create a staff user with an employee attached to a fresh team.
 *
 * @return array{0: User, 1: Employee, 2: Team}
 */
function memberOfTeam(): array
{
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create();
    $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    return [$user, $employee, $team];
}

// ── Render ──────────────────────────────────────────────────────────────────

it('redirects guests to login', function () {
    $this->get(route('team-recap.weekly'))->assertRedirect(route('login'));
});

it('renders the team weekly recap', function () {
    [$user, , $team] = memberOfTeam();

    $this->actingAs($user)
        ->get(route('team-recap.weekly'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/TeamWeeklyRecap')
            ->has('teams', 1)
            ->where('selectedTeamId', $team->id)
            ->has('segments')
            ->has('evidences')
            ->has('weekStart')
        );
});

it('renders the monthly recap', function () {
    [$user] = memberOfTeam();

    $this->actingAs($user)
        ->get(route('team-recap.monthly'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/MonthlyRecap')
            ->has('segments')
            ->has('year')
            ->has('month')
        );
});

it('renders the quarterly recap with PIC options', function () {
    [$user] = memberOfTeam();

    $this->actingAs($user)
        ->get(route('team-recap.quarterly'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/QuarterlyRecap')
            ->has('segments')
            ->has('quarter')
            ->has('pics')
        );
});

it('only lists teams the employee belongs to', function () {
    [$user, $employee] = memberOfTeam();
    Team::factory()->create(); // a team the employee is NOT in

    $this->actingAs($user)
        ->get(route('team-recap.weekly'))
        ->assertInertia(fn ($page) => $page->has('teams', 1));
});

// ── Evidence ──────────────────────────────────────────────────────────────

it('stores team recap evidence for a member', function () {
    [$user, $employee, $team] = memberOfTeam();

    $this->actingAs($user)
        ->post(route('team-recap.evidence.store'), [
            'team_id' => $team->id,
            'week_start' => '2026-06-01',
            'type' => 'notula',
            'title' => 'Notula rapat',
            'url' => 'https://example.test/notula',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('team_recap_evidences', [
        'team_id' => $team->id,
        'type' => 'notula',
        'url' => 'https://example.test/notula',
        'uploaded_by' => $employee->id,
    ]);
});

it('forbids storing evidence for a team the employee is not in', function () {
    [$user] = memberOfTeam();
    $otherTeam = Team::factory()->create();

    $this->actingAs($user)
        ->post(route('team-recap.evidence.store'), [
            'team_id' => $otherTeam->id,
            'week_start' => '2026-06-01',
            'type' => 'photo',
            'url' => 'https://example.test/foto',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('team_recap_evidences', ['team_id' => $otherTeam->id]);
});

it('deletes evidence for a member', function () {
    [$user, , $team] = memberOfTeam();
    $evidence = TeamRecapEvidence::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->delete(route('team-recap.evidence.destroy', $evidence->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('team_recap_evidences', ['id' => $evidence->id]);
});

// ── Override ──────────────────────────────────────────────────────────────

it('upserts a paraphrase override (updateOrCreate)', function () {
    [$user, , $team] = memberOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $payload = [
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'month',
        'period_year' => 2026,
        'period_month' => 6,
        'obstacle' => 'parafrase pertama',
    ];

    $this->actingAs($user)->post(route('team-recap.override.store'), $payload)->assertRedirect();
    $this->actingAs($user)->post(route('team-recap.override.store'), array_merge($payload, ['obstacle' => 'parafrase kedua']));

    expect(RecapOverride::where('performance_plan_id', $plan->id)->count())->toBe(1);
    expect(RecapOverride::where('performance_plan_id', $plan->id)->first()->obstacle)->toBe('parafrase kedua');
});

it('forbids overrides for a team the employee is not in', function () {
    [$user] = memberOfTeam();
    $otherTeam = Team::factory()->create();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $otherTeam->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $otherTeam->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'quarter',
            'period_year' => 2026,
            'period_quarter' => 2,
            'obstacle' => 'x',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('recap_overrides', ['team_id' => $otherTeam->id]);
});
