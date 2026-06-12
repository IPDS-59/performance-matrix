<?php

use App\Models\ActivityClaim;
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

/**
 * A PJ (team leader) — allowed to manage evidence + paraphrase.
 *
 * @return array{0: User, 1: Employee, 2: Team}
 */
function pjOfTeam(): array
{
    $user = staffUser();
    $employee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(['leader_id' => $employee->id]);
    $employee->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);

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

// ── PJ-preferred team and claim-anchored defaults ────────────────────────────

it('PJ hits weekly with no params and lands on the team they lead', function () {
    [$pjUser, $pjEmployee, $ledTeam] = pjOfTeam();

    // Also attach the PJ to an alphabetically-earlier member team so first() would
    // pick the wrong one without the PJ-preference logic.
    $otherTeam = Team::factory()->create(['name' => 'AAA Team']);
    $pjEmployee->teams()->attach($otherTeam->id, ['role' => 'member', 'is_primary' => false]);

    $this->actingAs($pjUser)
        ->get(route('team-recap.weekly'))
        ->assertInertia(fn ($page) => $page
            ->where('selectedTeamId', $ledTeam->id)
        );
});

it('PJ with no ?week param sees the week of the latest saved claim', function () {
    [$pjUser, $pjEmployee, $team] = pjOfTeam();

    $member = Employee::factory()->create();
    $team->members()->attach($member->id, ['role' => 'member', 'is_primary' => true]);

    $plan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $team->id,
    ]);

    $claimWeek = '2026-05-04'; // a Monday
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $member->id,
        'performance_plan_id' => $plan->id,
        'week_start' => $claimWeek,
        'period_year' => 2026,
        'period_month' => 5,
        'period_quarter' => 2,
    ]);

    $this->actingAs($pjUser)
        ->get(route('team-recap.weekly'))
        ->assertInertia(fn ($page) => $page
            ->where('weekStart', $claimWeek)
            ->where('selectedTeamId', $team->id)
        );
});

it('PJ weekly recap segments contain a member RK from a team-scoped claim', function () {
    [$pjUser, $pjEmployee, $team] = pjOfTeam();

    $member = Employee::factory()->create(['display_name' => 'Dewi']);
    $team->members()->attach($member->id, ['role' => 'member', 'is_primary' => true]);

    $plan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $team->id,
        'description' => 'RK Khusus Tim',
    ]);

    $claimWeek = '2026-06-01';
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $member->id,
        'performance_plan_id' => $plan->id,
        'week_start' => $claimWeek,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
    ]);

    $this->actingAs($pjUser)
        ->get(route('team-recap.weekly', ['week' => $claimWeek]))
        ->assertInertia(fn ($page) => $page
            ->where('selectedTeamId', $team->id)
            ->where('weekStart', $claimWeek)
            ->has('segments', 1)
        );
});

it('explicit ?team param overrides PJ preference', function () {
    [$pjUser, $pjEmployee, $ledTeam] = pjOfTeam();

    $otherTeam = Team::factory()->create();
    $pjEmployee->teams()->attach($otherTeam->id, ['role' => 'member', 'is_primary' => false]);

    $this->actingAs($pjUser)
        ->get(route('team-recap.weekly', ['team' => $otherTeam->id]))
        ->assertInertia(fn ($page) => $page
            ->where('selectedTeamId', $otherTeam->id)
        );
});

it('monthly with no params defaults to the period of the latest claim', function () {
    [$pjUser, , $team] = pjOfTeam();

    $plan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $team->id,
    ]);

    ActivityClaim::factory()->saved()->create([
        'employee_id' => Employee::factory(),
        'performance_plan_id' => $plan->id,
        'period_year' => 2025,
        'period_month' => 11,
        'period_quarter' => 4,
    ]);

    $this->actingAs($pjUser)
        ->get(route('team-recap.monthly'))
        ->assertInertia(fn ($page) => $page
            ->where('year', 2025)
            ->where('month', 11)
        );
});

it('quarterly with no params defaults to the period of the latest claim', function () {
    [$pjUser, , $team] = pjOfTeam();

    $plan = PerformancePlan::factory()->create([
        'project_id' => null,
        'team_id' => $team->id,
    ]);

    ActivityClaim::factory()->saved()->create([
        'employee_id' => Employee::factory(),
        'performance_plan_id' => $plan->id,
        'period_year' => 2025,
        'period_month' => 10,
        'period_quarter' => 4,
    ]);

    $this->actingAs($pjUser)
        ->get(route('team-recap.quarterly'))
        ->assertInertia(fn ($page) => $page
            ->where('year', 2025)
            ->where('quarter', 4)
        );
});

// ── Project-scoped RK claims still appear in weekly recap ────────────────────

it('project-scoped RK claim still appears in the weekly recap', function () {
    [$pjUser, , $team] = pjOfTeam();

    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => null]);

    $member = Employee::factory()->create();
    $team->members()->attach($member->id, ['role' => 'member', 'is_primary' => true]);

    $claimWeek = '2026-06-01';
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $member->id,
        'performance_plan_id' => $plan->id,
        'week_start' => $claimWeek,
        'period_year' => 2026,
        'period_month' => 6,
        'period_quarter' => 2,
    ]);

    $this->actingAs($pjUser)
        ->get(route('team-recap.weekly', ['week' => $claimWeek]))
        ->assertInertia(fn ($page) => $page
            ->has('segments', 1)
            ->where('segments.0.project_id', $project->id)
        );
});

// ── Evidence ──────────────────────────────────────────────────────────────

it('stores team recap evidence for a PJ', function () {
    [$user, $employee, $team] = pjOfTeam();

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

it('forbids a non-PJ member from storing evidence', function () {
    [$user, , $team] = memberOfTeam();

    $this->actingAs($user)
        ->post(route('team-recap.evidence.store'), [
            'team_id' => $team->id,
            'week_start' => '2026-06-01',
            'type' => 'notula',
            'url' => 'https://example.test/notula',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('team_recap_evidences', ['team_id' => $team->id]);
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

it('deletes evidence for a PJ', function () {
    [$user, , $team] = pjOfTeam();
    $evidence = TeamRecapEvidence::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->delete(route('team-recap.evidence.destroy', $evidence->id))
        ->assertRedirect();

    $this->assertDatabaseMissing('team_recap_evidences', ['id' => $evidence->id]);
});

it('forbids a non-PJ member from deleting evidence', function () {
    [$user, , $team] = memberOfTeam();
    $evidence = TeamRecapEvidence::factory()->create(['team_id' => $team->id]);

    $this->actingAs($user)
        ->delete(route('team-recap.evidence.destroy', $evidence->id))
        ->assertForbidden();

    $this->assertDatabaseHas('team_recap_evidences', ['id' => $evidence->id]);
});

it('exposes canManage true for a PJ and false for a member', function () {
    [$pjUser] = pjOfTeam();
    $this->actingAs($pjUser)
        ->get(route('team-recap.weekly'))
        ->assertInertia(fn ($page) => $page->where('canManage', true));

    [$memberUser] = memberOfTeam();
    $this->actingAs($memberUser)
        ->get(route('team-recap.weekly'))
        ->assertInertia(fn ($page) => $page->where('canManage', false));
});

// ── Override ──────────────────────────────────────────────────────────────

it('upserts a paraphrase override (updateOrCreate)', function () {
    [$user, , $team] = pjOfTeam();
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

it('forbids a non-PJ member from paraphrasing', function () {
    [$user, , $team] = memberOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'month',
            'period_year' => 2026,
            'period_month' => 6,
            'obstacle' => 'x',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('recap_overrides', ['performance_plan_id' => $plan->id]);
});

// ── Weekly override (storeOverride with period_type=week) ────────────────────

it('PJ stores a weekly paraphrase with only week_start (year derived server-side)', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'week_start' => '2026-06-01',
            'obstacle' => 'kendala PJ',
            'solution' => 'solusi PJ',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('recap_overrides', [
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'obstacle' => 'kendala PJ',
    ]);
});

it('non-PJ gets 403 on weekly storeOverride', function () {
    [$user, , $team] = memberOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'week_start' => '2026-06-01',
            'obstacle' => 'x',
        ])
        ->assertForbidden();
});

// ── confirmOverride ───────────────────────────────────────────────────────────

it('PJ can confirm a weekly override', function () {
    [$user, $employee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'confirmed' => true,
        ])
        ->assertRedirect();

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override)->not->toBeNull();
    expect($override->confirmed_at)->not->toBeNull();
    expect($override->confirmed_by)->toBe($employee->id);
});

it('confirming does not wipe existing paraphrase text', function () {
    [$user, $employee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    // Pre-existing paraphrase stored by PJ (period_month must be null to match
    // the confirmOverride key which sends period_month = null for week-type)
    RecapOverride::create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => '2026-06-01',
        'obstacle' => 'kendala sebelumnya',
        'solution' => null,
        'follow_up_plan' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
        'created_by' => null,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'confirmed' => true,
        ]);

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override->obstacle)->toBe('kendala sebelumnya');
    expect($override->confirmed_at)->not->toBeNull();
});

it('re-saving paraphrase does not clear confirmed_at', function () {
    [$user, $employee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    RecapOverride::create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => '2026-06-01',
        'obstacle' => 'lama',
        'solution' => null,
        'follow_up_plan' => null,
        'confirmed_at' => now(),
        'confirmed_by' => $employee->id,
        'created_by' => null,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'week_start' => '2026-06-01',
            'obstacle' => 'diperbarui',
        ]);

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override->obstacle)->toBe('diperbarui');
    expect($override->confirmed_at)->not->toBeNull();
    expect($override->confirmed_by)->toBe($employee->id);
});

it('non-PJ gets 403 on confirmOverride', function () {
    [$user, , $team] = memberOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'confirmed' => true,
        ])
        ->assertForbidden();
});

// ── confirmBulk ───────────────────────────────────────────────────────────────

it('PJ bulk-confirms several plans for a week', function () {
    [$user, $employee, $team] = pjOfTeam();

    $plan1 = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);
    $plan2 = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm-bulk'), [
            'team_id' => $team->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'performance_plan_ids' => [$plan1->id, $plan2->id],
        ])
        ->assertRedirect();

    foreach ([$plan1->id, $plan2->id] as $planId) {
        $override = RecapOverride::where('performance_plan_id', $planId)->first();
        expect($override)->not->toBeNull();
        expect($override->confirmed_at)->not->toBeNull();
        expect($override->confirmed_by)->toBe($employee->id);
    }
});

it('bulk confirm does NOT wipe an existing paraphrase on those rows', function () {
    [$user, $employee, $team] = pjOfTeam();

    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    RecapOverride::create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => '2026-06-01',
        'obstacle' => 'kendala sudah ada',
        'solution' => 'solusi sudah ada',
        'follow_up_plan' => null,
        'confirmed_at' => null,
        'confirmed_by' => null,
        'created_by' => null,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm-bulk'), [
            'team_id' => $team->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'performance_plan_ids' => [$plan->id],
        ]);

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override->obstacle)->toBe('kendala sudah ada');
    expect($override->solution)->toBe('solusi sudah ada');
    expect($override->confirmed_at)->not->toBeNull();
    expect($override->confirmed_by)->toBe($employee->id);
});

it('non-PJ gets 403 on confirm-bulk', function () {
    [$user, , $team] = memberOfTeam();

    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm-bulk'), [
            'team_id' => $team->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'performance_plan_ids' => [$plan->id],
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('recap_overrides', ['performance_plan_id' => $plan->id]);
});

it('PJ can unconfirm by posting confirmed=false', function () {
    [$user, $employee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
    ]);

    RecapOverride::create([
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'week',
        'period_year' => 2026,
        'period_month' => null,
        'period_quarter' => null,
        'week_start' => '2026-06-01',
        'obstacle' => null,
        'solution' => null,
        'follow_up_plan' => null,
        'confirmed_at' => now(),
        'confirmed_by' => $employee->id,
        'created_by' => null,
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'week',
            'period_year' => 2026,
            'week_start' => '2026-06-01',
            'confirmed' => false,
        ]);

    $override = RecapOverride::where('performance_plan_id', $plan->id)->first();
    expect($override->confirmed_at)->toBeNull();
    expect($override->confirmed_by)->toBeNull();
});
