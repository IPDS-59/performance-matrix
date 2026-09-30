<?php

use App\Models\ActivityClaim;
use App\Models\Employee;
use App\Models\LeadershipNote;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\RecapLock;
use App\Models\RecapOverride;
use App\Models\RecapSummary;
use App\Models\Team;
use App\Models\TeamRecapEvidence;
use App\Models\User;
use App\Services\Kinetik\RecapAggregator;

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

// ── PIC delegation (storeOverride) ───────────────────────────────────────────

/**
 * A team member who is the PIC for one plan but NOT a PJ and NOT the PIC for another.
 *
 * @return array{0: User, 1: Employee, 2: Team, 3: PerformancePlan, 4: PerformancePlan}
 */
function picOfOnePlan(): array
{
    $user = staffUser();
    $picEmployee = Employee::factory()->create(['user_id' => $user->id]);
    $team = Team::factory()->create(); // no leader_id set — PIC is NOT the PJ
    $picEmployee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    // Plan owned by this PIC
    $ownedPlan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
        'pic_employee_id' => $picEmployee->id,
    ]);

    // Plan owned by someone else (another employee)
    $otherPlan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
        'pic_employee_id' => Employee::factory()->create()->id,
    ]);

    return [$user, $picEmployee, $team, $ownedPlan, $otherPlan];
}

it('PIC can store a paraphrase override for their own plan', function () {
    [$user, $picEmployee, $team, $ownedPlan] = picOfOnePlan();

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $ownedPlan->id,
            'period_type' => 'month',
            'period_year' => 2026,
            'period_month' => 6,
            'obstacle' => 'kendala dari PIC',
            'solution' => 'solusi dari PIC',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('recap_overrides', [
        'team_id' => $team->id,
        'performance_plan_id' => $ownedPlan->id,
        'obstacle' => 'kendala dari PIC',
        'created_by' => $picEmployee->id,
    ]);
});

it('PIC gets 403 storing an override for a plan they do not own', function () {
    [$user, , $team, , $otherPlan] = picOfOnePlan();

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $otherPlan->id,
            'period_type' => 'month',
            'period_year' => 2026,
            'period_month' => 6,
            'obstacle' => 'x',
        ])
        ->assertForbidden();

    $this->assertDatabaseMissing('recap_overrides', ['performance_plan_id' => $otherPlan->id]);
});

it('PIC gets 403 on confirmOverride (confirm is PJ-only)', function () {
    [$user, , $team, $ownedPlan] = picOfOnePlan();

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [
            'team_id' => $team->id,
            'performance_plan_id' => $ownedPlan->id,
            'period_type' => 'month',
            'period_year' => 2026,
            'period_month' => 6,
            'confirmed' => true,
        ])
        ->assertForbidden();
});

it('PIC gets 403 on confirm-bulk (confirm is PJ-only)', function () {
    [$user, , $team, $ownedPlan] = picOfOnePlan();

    $this->actingAs($user)
        ->post(route('team-recap.override.confirm-bulk'), [
            'team_id' => $team->id,
            'period_type' => 'month',
            'period_year' => 2026,
            'period_month' => 6,
            'performance_plan_ids' => [$ownedPlan->id],
        ])
        ->assertForbidden();
});

it('PJ can still store an override for any plan regardless of PIC assignment', function () {
    [$pjUser, , $team] = pjOfTeam();

    // Plan with a different PIC
    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
        'pic_employee_id' => Employee::factory()->create()->id,
    ]);

    $this->actingAs($pjUser)
        ->post(route('team-recap.override.store'), [
            'team_id' => $team->id,
            'performance_plan_id' => $plan->id,
            'period_type' => 'month',
            'period_year' => 2026,
            'period_month' => 6,
            'obstacle' => 'parafrase PJ',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('recap_overrides', [
        'performance_plan_id' => $plan->id,
        'obstacle' => 'parafrase PJ',
    ]);
});

it('a plain member (not PIC, not PJ) gets 403 on storeOverride', function () {
    [$user, , $team] = memberOfTeam();

    $plan = PerformancePlan::factory()->create([
        'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
        'pic_employee_id' => Employee::factory()->create()->id, // someone else is PIC
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

// ── Head / admin office-wide view ────────────────────────────────────────────

it('lets the head see every team in the recap selector', function () {
    Team::factory()->count(3)->create();

    $this->actingAs(headUser())
        ->get(route('team-recap.monthly'))
        ->assertInertia(fn ($page) => $page
            ->has('teams', 3)
            ->where('canManage', false)
        );
});

it('keeps a staff member limited to their own teams', function () {
    [$user] = memberOfTeam();
    Team::factory()->count(2)->create();

    $this->actingAs($user)
        ->get(route('team-recap.monthly'))
        ->assertInertia(fn ($page) => $page->has('teams', 1));
});

// ── PJ uraian paraphrase ─────────────────────────────────────────────────────

it('saves the PJ uraian paraphrase', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    $this->actingAs($user)->post(route('team-recap.override.store'), [
        'team_id' => $team->id,
        'performance_plan_id' => $plan->id,
        'period_type' => 'month',
        'period_year' => 2026,
        'period_month' => 6,
        'uraian' => 'Uraian ringkas PJ',
    ])->assertRedirect();

    expect(RecapOverride::firstOrFail()->uraian)->toBe('Uraian ringkas PJ');
});

// ── Excel export ─────────────────────────────────────────────────────────────

it('exports the monthly recap for every team the head can see', function () {
    $team = Team::factory()->create(['name' => 'A MTI']);
    Team::factory()->create(['name' => 'B Umum']);
    $member = Employee::factory()->create(['team_id' => $team->id]);
    $project = Project::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $member->id,
        'performance_plan_id' => $plan->id, 'project_id' => $project->id,
        'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2, 'week_start' => '2026-06-01',
    ]);

    $this->actingAs(headUser())
        ->getJson(route('team-recap.export', ['period_type' => 'month', 'year' => 2026, 'month' => 6]))
        ->assertOk()
        ->assertJsonCount(2, 'teams')
        ->assertJsonPath('teams.0.team_name', 'A MTI')
        ->assertJsonPath('teams.0.segments.0.project_id', $project->id)
        ->assertJsonPath('teams.1.segments', []);
});

it('exports weekly evidence links grouped by type', function () {
    [$user, , $team] = memberOfTeam();
    TeamRecapEvidence::factory()->create([
        'team_id' => $team->id, 'period_type' => 'week', 'period_year' => 2026,
        'week_start' => '2026-06-01', 'type' => 'notula', 'url' => 'https://example.test/notula',
    ]);

    $this->actingAs($user)
        ->getJson(route('team-recap.export', ['period_type' => 'week', 'week' => '2026-06-03']))
        ->assertOk()
        ->assertJsonPath('week_start', '2026-06-01')
        ->assertJsonPath('teams.0.evidences.notula.0', 'https://example.test/notula');
});

it('validates the export period', function () {
    [$user] = memberOfTeam();

    $this->actingAs($user)
        ->getJson(route('team-recap.export', ['period_type' => 'month']))
        ->assertUnprocessable();
});

// ── PJ lock before the meeting ───────────────────────────────────────────────

it('lets the PJ lock a month and then blocks paraphrase and sign-off', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $period = ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6];

    $this->actingAs($user)->post(route('team-recap.lock'), [...$period, 'locked' => true])->assertRedirect();

    $this->actingAs($user)
        ->get(route('team-recap.monthly', ['team' => $team->id, 'year' => 2026, 'month' => 6]))
        ->assertInertia(fn ($page) => $page
            ->where('canManage', false)
            ->where('canLock', true)
            ->whereNot('lock', null)
        );

    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [...$period, 'performance_plan_id' => $plan->id, 'obstacle' => 'x'])
        ->assertSessionHas('error');
    $this->actingAs($user)
        ->post(route('team-recap.override.confirm'), [...$period, 'performance_plan_id' => $plan->id, 'confirmed' => true])
        ->assertSessionHas('error');

    expect(RecapOverride::count())->toBe(0);

    // Unlock re-opens the period.
    $this->actingAs($user)->post(route('team-recap.lock'), [...$period, 'locked' => false]);
    $this->actingAs($user)
        ->post(route('team-recap.override.store'), [...$period, 'performance_plan_id' => $plan->id, 'obstacle' => 'x'])
        ->assertSessionMissing('error');
    expect(RecapOverride::count())->toBe(1);
});

it('blocks weekly evidence when the week is locked', function () {
    [$user, , $team] = pjOfTeam();
    $this->actingAs($user)->post(route('team-recap.lock'), [
        'team_id' => $team->id, 'period_type' => 'week', 'period_year' => 2026, 'week_start' => '2026-06-01', 'locked' => true,
    ]);

    $this->actingAs($user)->post(route('team-recap.evidence.store'), [
        'team_id' => $team->id, 'week_start' => '2026-06-01', 'type' => 'notula', 'url' => 'https://example.test/n',
    ])->assertSessionHas('error');

    expect(TeamRecapEvidence::count())->toBe(0);
});

it('does not let a non-PJ member lock a recap', function () {
    [$user, , $team] = memberOfTeam();

    $this->actingAs($user)->post(route('team-recap.lock'), [
        'team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6, 'locked' => true,
    ])->assertForbidden();
});

it('sends member completeness with the weekly recap', function () {
    [$user, $employee, $team] = pjOfTeam();

    $this->actingAs($user)
        ->get(route('team-recap.weekly', ['team' => $team->id, 'week' => '2026-06-01']))
        ->assertInertia(fn ($page) => $page
            ->has('members', 1)
            ->where('members.0.employee_id', $employee->id)
            ->where('members.0.status', 'no_activity')
        );
});

// ── All-teams overview ───────────────────────────────────────────────────────

it('gives the head one overview row per team with capaian, sign-off and lock', function () {
    $team = Team::factory()->create(['name' => 'A MTI']);
    Team::factory()->create(['name' => 'B Umum']);
    $member = Employee::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $member->id, 'performance_plan_id' => $plan->id,
        'target' => 4, 'realization' => 2, 'achievement' => 50,
        'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2, 'week_start' => '2026-06-01',
    ]);
    RecapLock::create(['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6]);

    $this->actingAs(headUser())
        ->get(route('team-recap.overview', ['period_type' => 'month', 'year' => 2026, 'month' => 6]))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/RecapOverview')
            ->where('teams.0.name', 'A MTI')
            ->where('teams.0.rows', 1)
            ->where('teams.0.avg_achievement', 50)
            ->where('teams.0.locked', true)
            ->where('teams.1.rows', 0)
            ->where('teams.1.avg_achievement', null)
            ->where('teams.1.locked', false)
        );
});

it('adds member completeness to the weekly overview', function () {
    [$user, , $team] = pjOfTeam();

    $this->actingAs($user)
        ->get(route('team-recap.overview', ['period_type' => 'week', 'week' => '2026-06-03']))
        ->assertInertia(fn ($page) => $page
            ->where('weekStart', '2026-06-01')
            ->where('teams.0.id', $team->id)
            ->where('teams.0.members_active', 0)
        );
});

it('shows the head each team PJ and each project with its PIC', function () {
    $pj = Employee::factory()->create(['display_name' => 'Hespri']);
    $team = Team::factory()->create(['name' => 'MTI', 'leader_id' => $pj->id]);
    $project = Project::factory()->create(['team_id' => $team->id, 'leader_id' => $pj->id, 'year' => 2026, 'name' => 'Kinetik']);
    $project->members()->attach($pj->id, ['role' => 'leader']);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $pj->id, 'performance_plan_id' => $plan->id, 'project_id' => $project->id,
        'achievement' => 80, 'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2, 'week_start' => '2026-06-01',
    ]);

    $this->actingAs(headUser())
        ->get(route('team-recap.overview', ['period_type' => 'month', 'year' => 2026, 'month' => 6]))
        ->assertInertia(fn ($page) => $page
            ->where('teams.0.leader', 'Hespri')
            ->where('teams.0.projects.0.name', 'Kinetik')
            ->where('teams.0.projects.0.leader', 'Hespri')
            ->where('teams.0.projects.0.members', 1)
            ->where('teams.0.projects.0.rows', 1)
        );
});

// ── Catatan Pimpinan (Review Bersama) ────────────────────────────────────────

it('lets the head write, update and clear a note on a locked team period', function () {
    $team = Team::factory()->create();
    $project = Project::factory()->create(['team_id' => $team->id, 'year' => 2026]);
    RecapLock::create(['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6]);
    $head = headUser();
    $period = ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6];

    $this->actingAs($head)->post(route('team-recap.leadership-note'), [...$period, 'body' => 'Percepat pencacahan'])->assertRedirect();
    $this->actingAs($head)->post(route('team-recap.leadership-note'), [...$period, 'body' => 'Sudah dibahas'])->assertRedirect();
    $this->actingAs($head)->post(route('team-recap.leadership-note'), [...$period, 'project_id' => $project->id, 'body' => 'Cek PIC'])->assertRedirect();

    expect(LeadershipNote::count())->toBe(2)
        ->and(LeadershipNote::whereNull('project_id')->value('body'))->toBe('Sudah dibahas');

    $this->actingAs($head)
        ->get(route('team-recap.overview', ['period_type' => 'month', 'year' => 2026, 'month' => 6]))
        ->assertInertia(fn ($page) => $page
            ->where('canWriteNotes', true)
            ->where('teams.0.note', 'Sudah dibahas')
            ->where('teams.0.projects.0.note', 'Cek PIC')
        );

    $this->actingAs($head)->post(route('team-recap.leadership-note'), [...$period, 'body' => ''])->assertRedirect();
    expect(LeadershipNote::whereNull('project_id')->exists())->toBeFalse();
});

it('keeps weekly notes on the monday of the week', function () {
    $team = Team::factory()->create();
    $period = ['team_id' => $team->id, 'period_type' => 'week', 'period_year' => 2026, 'week_start' => '2026-06-03'];

    $this->actingAs(headUser())->post(route('team-recap.leadership-note'), [...$period, 'body' => 'A']);
    $this->actingAs(headUser())->post(route('team-recap.leadership-note'), [...$period, 'body' => 'B']);

    expect(LeadershipNote::count())->toBe(1)
        ->and(LeadershipNote::first()->week_start)->toBe('2026-06-01');
});

it('forbids notes from anyone but the head', function () {
    [$user, , $team] = pjOfTeam();

    $this->actingAs($user)
        ->post(route('team-recap.leadership-note'), ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6, 'body' => 'x'])
        ->assertForbidden();
});

it('rejects a note on a project of another team', function () {
    $team = Team::factory()->create();
    $other = Project::factory()->create(['year' => 2026]);

    $this->actingAs(headUser())
        ->post(route('team-recap.leadership-note'), ['team_id' => $team->id, 'project_id' => $other->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6, 'body' => 'x'])
        ->assertStatus(422);
});

// ── Pre-fill from lower periods ──────────────────────────────────────────────

function weekOverride(Team $team, PerformancePlan $plan, string $week, array $text): RecapOverride
{
    return RecapOverride::create([
        'team_id' => $team->id, 'performance_plan_id' => $plan->id, 'project_id' => null,
        'period_type' => 'week', 'period_year' => 2026, 'week_start' => $week, ...$text,
    ]);
}

it('fills the empty monthly text from the weeks and keeps what the PJ wrote', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    weekOverride($team, $plan, '2026-06-01', ['obstacle' => 'Hujan', 'follow_up_plan' => 'Minggu 1']);
    weekOverride($team, $plan, '2026-06-08', ['solution' => 'Tambah petugas', 'follow_up_plan' => 'Minggu 2']);
    weekOverride($team, $plan, '2026-07-06', ['follow_up_plan' => 'Bulan lain']);
    RecapOverride::create([
        'team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'month',
        'period_year' => 2026, 'period_month' => 6, 'obstacle' => 'Tulisan PJ',
    ]);

    $this->actingAs($user)
        ->post(route('team-recap.prefill'), ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6])
        ->assertSessionHas('success');

    $month = RecapOverride::where('period_type', 'month')->sole();
    expect($month->follow_up_plan)->toBe("Minggu 1\nMinggu 2")
        ->and($month->obstacle)->toBe('Tulisan PJ')
        ->and($month->solution)->toBe('Tambah petugas');
});

it('fills the quarter from the months first, then from the weeks', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    RecapOverride::create([
        'team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'month',
        'period_year' => 2026, 'period_month' => 4, 'obstacle' => 'Ringkasan April',
    ]);
    weekOverride($team, $plan, '2026-05-04', ['obstacle' => 'Minggu Mei', 'follow_up_plan' => 'Rapat evaluasi']);

    $this->actingAs($user)
        ->post(route('team-recap.prefill'), ['team_id' => $team->id, 'period_type' => 'quarter', 'period_year' => 2026, 'period_quarter' => 2]);

    $quarter = RecapOverride::where('period_type', 'quarter')->sole();
    expect($quarter->obstacle)->toBe('Ringkasan April')
        ->and($quarter->follow_up_plan)->toBe('Rapat evaluasi');
});

it('blocks pre-fill on a locked month and for non-PJ members', function () {
    [$user, , $team] = pjOfTeam();
    $params = ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6];

    $member = User::factory()->create();
    Employee::factory()->create(['user_id' => $member->id, 'team_id' => $team->id]);
    $this->actingAs($member)->post(route('team-recap.prefill'), $params)->assertForbidden();

    RecapLock::create(['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6]);
    $this->actingAs($user)->post(route('team-recap.prefill'), $params)->assertSessionHas('error');
});

// ── Gabungkan / Pisahkan ─────────────────────────────────────────────────────

function claimFor(Employee $employee, PerformancePlan $plan, Project $project, float $achievement): void
{
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $employee->id, 'performance_plan_id' => $plan->id, 'project_id' => $project->id,
        'target' => 1, 'realization' => $achievement / 100, 'achievement' => $achievement,
        'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2, 'week_start' => '2026-06-01',
    ]);
}

it('merges rows of one project into one text and splits them again', function () {
    [$user, $pj, $team] = pjOfTeam();
    $project = Project::factory()->create(['team_id' => $team->id, 'year' => 2026]);
    [$a, $b, $c] = PerformancePlan::factory()->count(3)->create(['project_id' => $project->id, 'team_id' => $team->id]);
    claimFor($pj, $a, $project, 50);
    claimFor($pj, $b, $project, 90);
    claimFor($pj, $c, $project, 70);
    // Legacy paraphrase of B without a Projek: moves to the Projek on merge.
    RecapOverride::create([
        'team_id' => $team->id, 'performance_plan_id' => $b->id, 'project_id' => null,
        'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6, 'solution' => 'Solusi B',
    ]);
    $period = ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6];

    $this->actingAs($user)
        ->post(route('team-recap.merge'), [...$period, 'project_id' => $project->id, 'performance_plan_ids' => [$c->id, $a->id]])
        ->assertSessionHas('success');

    $key = $c->id.':'.$project->id;
    expect(RecapOverride::where('merge_key', $key)->count())->toBe(2);

    $this->actingAs($user)->post(route('team-recap.override.store'), [
        ...$period, 'performance_plan_id' => $c->id, 'project_id' => $project->id, 'uraian' => 'Uraian gabungan',
    ]);

    $rows = collect(app(RecapAggregator::class)->monthly($team, 2026, 6)[0]['rows']);
    $lead = $rows->firstWhere('performance_plan_id', $c->id);
    $member = $rows->firstWhere('performance_plan_id', $a->id);
    expect($member['pj_uraian'])->toBe('Uraian gabungan')
        ->and($member['merge_key'])->toBe($key)
        ->and($member['achievement'])->toEqual(50)
        ->and($rows->search(fn ($r) => $r === $member))->toBe($rows->search(fn ($r) => $r === $lead) + 1)
        ->and($rows->firstWhere('performance_plan_id', $b->id)['pj_solution'])->toBe('Solusi B');

    $this->actingAs($user)->post(route('team-recap.split'), [...$period, 'merge_key' => $key])->assertSessionHas('success');
    expect(RecapOverride::whereNotNull('merge_key')->count())->toBe(0);
});

it('lets only the PJ merge, and not on a locked period', function () {
    [$user, , $team] = pjOfTeam();
    [$a, $b] = PerformancePlan::factory()->count(2)->create(['project_id' => null, 'team_id' => $team->id]);
    $params = ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6, 'performance_plan_ids' => [$a->id, $b->id]];

    $member = User::factory()->create();
    Employee::factory()->create(['user_id' => $member->id, 'team_id' => $team->id]);
    $this->actingAs($member)->post(route('team-recap.merge'), $params)->assertForbidden();

    RecapLock::create(['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6]);
    $this->actingAs($user)->post(route('team-recap.merge'), $params)->assertSessionHas('error');
    expect(RecapOverride::count())->toBe(0);
});

it('keeps fields the form did not send when saving a paraphrase', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $period = ['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6];

    $this->actingAs($user)->post(route('team-recap.override.store'), [...$period, 'obstacle' => 'Hujan']);
    $this->actingAs($user)->post(route('team-recap.override.store'), [...$period, 'solution' => 'Tambah petugas']);

    expect(RecapOverride::sole())->obstacle->toBe('Hujan')->solution->toBe('Tambah petugas');
});

// ── Member lines and PJ corrections ─────────────────────────────────────────

it('lists member claims per row with the RK Ketua, and lets the PJ correct numbers', function () {
    [$user, $pj, $team] = pjOfTeam();
    $project = Project::factory()->create(['team_id' => $team->id, 'year' => 2026, 'leader_rk' => 'RK Ketua Metodologi']);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => $team->id]);
    $member = Employee::factory()->create(['display_name' => 'Sukma']);
    $claim = ActivityClaim::factory()->saved()->create([
        'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'project_id' => $project->id,
        'target' => 4, 'realization' => 2, 'achievement' => 50, 'target_unit' => 'Dokumen',
        'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2, 'week_start' => '2026-06-01', 'activity_date_start' => '2026-06-02',
    ]);

    $this->actingAs($user)->post(route('team-recap.claim-adjust', $claim), ['target' => 4, 'realization' => 3])->assertSessionHas('success');
    expect($claim->fresh())->achievement->toEqual(75)->adjusted_by->toBe($pj->id);

    $this->actingAs($user)->get(route('team-recap.weekly', ['team' => $team->id, 'week' => '2026-06-01']))
        ->assertInertia(fn ($page) => $page
            ->where('segments.0.leader_rk', 'RK Ketua Metodologi')
            ->where('segments.0.rows.0.claims.0.name', 'Sukma')
            ->where('segments.0.rows.0.claims.0.realization', 3)
            ->where('segments.0.rows.0.claims.0.adjusted_by', $pj->display_name ?? $pj->name));
});

it('blocks PJ corrections for non-PJ users and locked periods', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $claim = ActivityClaim::factory()->saved()->create(['performance_plan_id' => $plan->id, 'activity_date_start' => '2026-06-02', 'week_start' => '2026-06-01', 'period_year' => 2026, 'period_month' => 6]);

    $member = User::factory()->create();
    Employee::factory()->create(['user_id' => $member->id]);
    $this->actingAs($member)->post(route('team-recap.claim-adjust', $claim), ['target' => 1, 'realization' => 1])->assertForbidden();

    RecapLock::create(['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6]);
    $this->actingAs($user)->post(route('team-recap.claim-adjust', $claim), ['target' => 1, 'realization' => 1])->assertSessionHas('error');
});

// ── Weekly sections and Ringkasan per Projek ────────────────────────────────

it('shows the weeks of a month and saves one summary per Projek', function () {
    [$user, $pj, $team] = pjOfTeam();
    $project = Project::factory()->create(['team_id' => $team->id, 'year' => 2026]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => $team->id]);
    ActivityClaim::factory()->saved()->create([
        'employee_id' => $pj->id, 'performance_plan_id' => $plan->id, 'project_id' => $project->id,
        'period_year' => 2026, 'period_month' => 6, 'period_quarter' => 2, 'week_start' => '2026-06-08', 'activity_date_start' => '2026-06-09',
    ]);
    $period = ['team_id' => $team->id, 'period_type' => 'month', 'period_year' => 2026, 'period_month' => 6];

    $this->actingAs($user)->post(route('team-recap.summary'), [...$period, 'project_id' => $project->id, 'body' => 'Sakernas berjalan lancar'])->assertSessionHas('success');
    $this->actingAs($user)->post(route('team-recap.summary'), [...$period, 'project_id' => $project->id, 'body' => 'Sakernas selesai'])->assertSessionHas('success');
    expect(RecapSummary::count())->toBe(1);

    // June 2026: weeks starting 1, 8, 15, 22, 29 June.
    $this->actingAs($user)->get(route('team-recap.monthly', ['team' => $team->id, 'year' => 2026, 'month' => 6]))
        ->assertInertia(fn ($page) => $page
            ->has('sections', 5)
            ->where('sections.0.start', '2026-06-01')
            ->where('sections.1.segments.0.project_id', $project->id)
            ->where("summaries.{$project->id}", 'Sakernas selesai'));

    $this->actingAs($user)->post(route('team-recap.summary'), [...$period, 'project_id' => $project->id, 'body' => '']);
    expect(RecapSummary::count())->toBe(0);
});

it('shows the months of a quarter and keeps summaries PJ-only and unlocked', function () {
    [$user, , $team] = pjOfTeam();
    $period = ['team_id' => $team->id, 'period_type' => 'quarter', 'period_year' => 2026, 'period_quarter' => 2, 'body' => 'x'];

    $this->actingAs($user)->get(route('team-recap.quarterly', ['team' => $team->id, 'year' => 2026, 'quarter' => 2]))
        ->assertInertia(fn ($page) => $page->has('sections', 3)->where('sections.0.start', '2026-04-01'));

    $member = User::factory()->create();
    Employee::factory()->create(['user_id' => $member->id]);
    $this->actingAs($member)->post(route('team-recap.summary'), $period)->assertForbidden();

    RecapLock::create(['team_id' => $team->id, 'period_type' => 'quarter', 'period_year' => 2026, 'period_quarter' => 2]);
    $this->actingAs($user)->post(route('team-recap.summary'), $period)->assertSessionHas('error');
});
