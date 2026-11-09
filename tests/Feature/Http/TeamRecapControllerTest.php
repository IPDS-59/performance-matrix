<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\RecapLock;
use App\Models\RecapOverride;
use App\Models\RkRecap;
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

it('lets the PJ lock a quarter and then blocks paraphrase and sign-off', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $period = ['team_id' => $team->id, 'period_type' => 'quarter', 'period_year' => 2026, 'period_quarter' => 2];

    $this->actingAs($user)->post(route('team-recap.lock'), [...$period, 'locked' => true])->assertRedirect();

    $this->actingAs($user)
        ->get(route('team-recap.quarterly', ['team' => $team->id, 'year' => 2026, 'quarter' => 2]))
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

it('does not let a non-PJ member lock a recap', function () {
    [$user, , $team] = memberOfTeam();

    $this->actingAs($user)->post(route('team-recap.lock'), [
        'team_id' => $team->id, 'period_type' => 'quarter', 'period_year' => 2026, 'period_quarter' => 2, 'locked' => true,
    ])->assertForbidden();
});

// ── Pre-fill from lower periods ──────────────────────────────────────────────

function weekOverride(Team $team, PerformancePlan $plan, string $week, array $text): RecapOverride
{
    return RecapOverride::create([
        'team_id' => $team->id, 'performance_plan_id' => $plan->id, 'project_id' => null,
        'period_type' => 'week', 'period_year' => 2026, 'week_start' => $week, ...$text,
    ]);
}

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

// ── Per-RK paraphrase, target and capaian (week, month) ─────────────────────

function rkPayload(Team $team, PerformancePlan $plan, array $extra = []): array
{
    return ['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => '2026-10-14', ...$extra];
}

it('lets the PJ save the paraphrase, target, satuan and capaian of an RK for a week', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    $this->actingAs($user)
        ->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Tim menyelesaikan skema.', 'target' => 10, 'target_unit' => 'tabel', 'capaian' => 8]))
        ->assertSessionHas('success');

    $recap = RkRecap::sole();
    expect($recap->period_start->toDateString())->toBe('2026-10-12')
        ->and($recap->paraphrase)->toBe('Tim menyelesaikan skema.')
        ->and($recap->target)->toBe('10.00')->and($recap->capaian)->toBe('8.00')->and($recap->target_unit)->toBe('tabel')
        ->and($recap->isLocked())->toBeFalse();

    // Saving again updates the same row.
    $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Revisi', 'target' => 10, 'capaian' => 9]));
    expect(RkRecap::count())->toBe(1)->and(RkRecap::sole()->paraphrase)->toBe('Revisi');
});

it('validates the RK numbers and keeps them numeric', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    foreach ([['target' => 'banyak'], ['capaian' => 'sembilan'], ['capaian' => -2], ['period_type' => 'quarter']] as $bad) {
        $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, $bad))->assertSessionHasErrors(array_key_first($bad));
    }
    expect(RkRecap::count())->toBe(0);
});

it('saves an RK paraphrase, lets the PJ edit it again, and ignores an old lock', function () {
    [$user, $pj, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Awal']))->assertSessionHas('success');
    $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Diubah']))->assertSessionHas('success');
    expect(RkRecap::sole()->paraphrase)->toBe('Diubah');

    // A recap that was locked before the lock was removed can still be edited.
    RkRecap::sole()->update(['locked_at' => now(), 'locked_by' => $pj->id]);
    $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Setelah kunci lama']))->assertSessionHas('success');
    expect(RkRecap::sole()->paraphrase)->toBe('Setelah kunci lama');
});

it('lets the RK PIC write but not a plain member or a stranger', function () {
    [$memberUser, $member, $team] = memberOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    $this->actingAs($memberUser)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'x']))->assertForbidden();

    $plan->update(['pic_employee_id' => $member->id]);
    $this->actingAs($memberUser)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'x']))->assertSessionHas('success');

    $otherPlan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => Team::factory()->create()->id]);
    $this->actingAs($memberUser)->post(route('team-recap.rk.store'), rkPayload($team, $otherPlan))->assertStatus(422);
});

function recapItem(Team $team, Employee $employee, PerformancePlan $plan, array $extra = []): PlanItem
{
    return PlanItem::create([
        'team_id' => $team->id, 'employee_id' => $employee->id, 'performance_plan_id' => $plan->id,
        'description' => 'Entri data', 'date_start' => '2026-10-13', 'date_end' => '2026-10-14', ...$extra,
    ]);
}

it('shows the weekly panel of an RK with the members entries and the PJ recap', function () {
    [$user, $pj, $team] = pjOfTeam();
    $andi = Employee::factory()->create(['display_name' => 'Andi']);
    $andi->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Inovasi']);
    recapItem($team, $andi, $plan, ['capaian_note' => 'Skema selesai. Migrasi jalan.', 'target' => 4, 'capaian' => 3, 'target_unit' => 'tabel']);
    recapItem($team, $andi, $plan, ['description' => 'Rapat', 'target' => 1, 'capaian' => 1, 'target_unit' => 'tabel']);
    recapItem($team, $andi, $plan, ['description' => 'Batal', 'status' => 'cancelled']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-09-01', 'date_end' => '2026-09-02']); // another week
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => '2026-10-12', 'paraphrase' => 'Ringkas']);

    $this->actingAs($user)->get(route('team-recap.weekly', ['week' => '2026-10-15']))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/TeamWeeklyRecap')
            ->where('weekStart', '2026-10-12')
            ->where('canManage', true)
            ->has('panels', 1)
            ->where('panels.0.rk_description', 'RK Inovasi')
            ->where('panels.0.entry_count', 2)
            ->where('panels.0.recap.paraphrase', 'Ringkas')
            ->has('panels.0.entries', 1)
            ->where('panels.0.entries.0.name', 'Andi')
            ->where('panels.0.entries.0.lines', ['Skema selesai.', 'Migrasi jalan.', 'Rapat'])
            ->where('panels.0.entries.0.target', 5)
            ->where('panels.0.entries.0.capaian', 4));
});

it('opens the weekly recap on the week of the latest plan item', function () {
    [$user, $pj, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    recapItem($team, $pj, $plan, ['date_start' => '2026-08-04', 'date_end' => '2026-08-05']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-09-16', 'date_end' => '2026-09-16']);

    // The weekly page opens on the months of the year of the latest plan item.
    $this->actingAs($user)->get(route('team-recap.weekly'))->assertInertia(fn ($page) => $page->where('view', 'months')->where('year', 2026));
    // The monthly page opens on the list of months, in the year of the latest plan item.
    $this->actingAs($user)->get(route('team-recap.monthly'))->assertInertia(fn ($page) => $page->where('view', 'list')->where('year', 2026)->where('month', null));
    $this->actingAs($user)->get(route('team-recap.quarterly'))->assertInertia(fn ($page) => $page->where('view', 'list')->where('year', 2026));
});

it('shows the weekly paraphrases of the month as context on the monthly panel', function () {
    [$user, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    foreach (['2026-10-05' => 'Minggu satu', '2026-10-12' => 'Minggu dua', '2026-09-28' => 'Bulan lalu'] as $start => $text) {
        RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => $start, 'paraphrase' => $text]);
    }

    $this->actingAs($user)->get(route('team-recap.monthly', ['year' => 2026, 'month' => 10]))
        ->assertInertia(fn ($page) => $page
            ->has('panels.0.weeks', 2)
            ->where('panels.0.weeks.0.paraphrase', 'Minggu satu')
            ->where('panels.0.weeks.1.paraphrase', 'Minggu dua'));
});

it('reads the quarterly recap from plan items and the PJ month numbers', function () {
    [$user, $pj, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Triwulan']);
    $other = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Lain']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-10-05', 'date_end' => '2026-10-05', 'target' => 10, 'capaian' => 5, 'target_unit' => 'berkas']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-11-03', 'date_end' => '2026-11-03', 'target' => 10, 'capaian' => 10]);
    recapItem($team, $pj, $other, ['date_start' => '2026-10-05', 'date_end' => '2026-10-05', 'target' => 4, 'capaian' => 4]);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $other->id, 'period_type' => 'month', 'period_start' => '2026-10-01', 'target' => 8, 'capaian' => 2]);

    $this->actingAs($user)->get(route('team-recap.quarterly', ['year' => 2026, 'quarter' => 4]))
        ->assertInertia(function ($page) {
            $rows = collect($page->toArray()['props']['segments'])->flatMap(fn ($s) => $s['rows'])->keyBy('rk_description');

            expect($rows['RK Triwulan'])->target->toEqual(20)->realization->toEqual(15)->achievement->toEqual(75)->target_unit->toBe('berkas')
                // The PJ month numbers win over the members' sums.
                ->and($rows['RK Lain'])->target->toEqual(8)->realization->toEqual(2)->achievement->toEqual(25);
        });
});

it('reports how many plan items of each member carry a capaian', function () {
    [$user, $pj, $team] = pjOfTeam();
    $andi = Employee::factory()->create(['display_name' => 'Andi']);
    $andi->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    recapItem($team, $andi, $plan, ['capaian' => 1]);
    recapItem($team, $andi, $plan);

    $this->actingAs($user)->get(route('team-recap.weekly', ['week' => '2026-10-13']))
        ->assertInertia(function ($page) {
            $members = collect($page->toArray()['props']['members'])->keyBy('name');

            expect($members['Andi'])->total->toBe(2)->saved->toBe(1)->status->toBe('partial')
                ->and($members->first(fn ($m) => $m['name'] !== 'Andi'))->status->toBe('no_activity');
        });
});

it('exports the weekly recap rows from plan items', function () {
    [$user, $pj, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'RK Ekspor']);
    recapItem($team, $pj, $plan, ['target' => 2, 'capaian' => 1]);

    $this->actingAs($user)->getJson(route('team-recap.export', ['period_type' => 'week', 'week' => '2026-10-14']))
        ->assertOk()
        ->assertJsonPath('teams.0.segments.0.rows.0.rk_description', 'RK Ekspor')
        ->assertJsonPath('teams.0.segments.0.rows.0.achievement', 50);
});

it('lists the months of the year with their counts and opens one month on click', function () {
    [$user, $pj, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    recapItem($team, $pj, $plan, ['date_start' => '2026-09-16', 'date_end' => '2026-09-16']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-09-18', 'date_end' => '2026-09-18']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-10-05', 'date_end' => '2026-10-05']);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'month', 'period_start' => '2026-09-01', 'paraphrase' => 'Ringkasan']);

    $this->actingAs($user)->get(route('team-recap.monthly', ['year' => 2026]))->assertInertia(fn ($page) => $page
        ->where('view', 'list')
        ->where('panels', [])
        ->where('months.8.label', 'September')->where('months.8.activities', 2)->where('months.8.written', 1)->where('months.8.rks', 1)
        ->where('months.9.activities', 1)
        ->where('months.0.activities', 0));

    $this->actingAs($user)->get(route('team-recap.monthly', ['year' => 2026, 'month' => 9]))->assertInertia(fn ($page) => $page
        ->where('view', 'detail')->where('month', 9)->where('months', []));
});

it('walks the weekly recap from the months to the weeks of a month to one week', function () {
    [$user, $pj, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    recapItem($team, $pj, $plan, ['date_start' => '2026-09-16', 'date_end' => '2026-09-16']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-09-17', 'date_end' => '2026-09-17']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-09-23', 'date_end' => '2026-09-23']);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => '2026-09-14', 'paraphrase' => 'Ringkasan minggu']);

    $this->actingAs($user)->get(route('team-recap.weekly', ['year' => 2026]))->assertInertia(fn ($page) => $page
        ->where('view', 'months')->where('periods.8.activities', 3));

    $this->actingAs($user)->get(route('team-recap.weekly', ['year' => 2026, 'month' => 9]))->assertInertia(fn ($page) => $page
        ->where('view', 'weeks')->where('month', 9)
        ->where('periods', fn ($weeks) => collect($weeks)->firstWhere('key', '2026-09-14')['activities'] === 2
            && collect($weeks)->firstWhere('key', '2026-09-14')['written'] === 1
            && collect($weeks)->firstWhere('key', '2026-09-21')['activities'] === 1
            && collect($weeks)->first()['key'] === '2026-08-31'));

    $this->actingAs($user)->get(route('team-recap.weekly', ['week' => '2026-09-16']))->assertInertia(fn ($page) => $page
        ->where('view', 'detail')->where('weekStart', '2026-09-14')->where('periods', [])
        ->where('plans', fn ($plans) => count($plans) === 2
            && collect($plans)->pluck('description')->every(fn ($d) => is_string($d))
            && array_key_exists('employee_name', $plans[0])));
});

it('shows the PJ the plans of every member of the week, read only, and none from other weeks', function () {
    [$user, $pj, $team] = pjOfTeam();
    $other = Employee::factory()->create(['display_name' => 'Andi']);
    $other->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    recapItem($team, $other, $plan, ['description' => 'Rencana Andi', 'date_start' => '2026-09-15', 'date_end' => '2026-09-16']);
    recapItem($team, $other, $plan, ['description' => 'Minggu lain', 'date_start' => '2026-09-01', 'date_end' => '2026-09-02']);
    recapItem($team, $other, $plan, ['description' => 'Batal', 'date_start' => '2026-09-15', 'date_end' => '2026-09-15', 'status' => 'cancelled']);

    $this->actingAs($user)->get(route('team-recap.weekly', ['week' => '2026-09-14']))->assertInertia(fn ($page) => $page
        ->where('plans', fn ($plans) => count($plans) === 1 && $plans[0]['employee_name'] === 'Andi' && $plans[0]['description'] === 'Rencana Andi'));
});

it('lists the four quarters with their counts and opens one on click', function () {
    [$user, $pj, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    recapItem($team, $pj, $plan, ['date_start' => '2026-09-16', 'date_end' => '2026-09-16']);
    recapItem($team, $pj, $plan, ['date_start' => '2026-10-05', 'date_end' => '2026-10-05']);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'month', 'period_start' => '2026-09-01', 'paraphrase' => 'Ringkasan']);

    $this->actingAs($user)->get(route('team-recap.quarterly', ['year' => 2026]))->assertInertia(fn ($page) => $page
        ->where('view', 'list')->where('segments', [])
        ->where('periods.2.label', 'Triwulan 3')->where('periods.2.activities', 1)->where('periods.2.written', 1)
        ->where('periods.3.activities', 1)->where('periods.0.activities', 0));

    $this->actingAs($user)->get(route('team-recap.quarterly', ['year' => 2026, 'quarter' => 3]))->assertInertia(fn ($page) => $page
        ->where('view', 'detail')->where('quarter', 3)->where('periods', []));
});

it('does not let the head write a paraphrase even when the head leads the team', function () {
    [$user, , $team] = pjOfTeam();
    $user->assignRole('head');
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Dari Kepala']))->assertForbidden();

    expect(RkRecap::count())->toBe(0);
});

it('opens Review Bersama to a PJ with every team (the spv asked on 9 Nov 2026), and keeps it closed to a plain member', function () {
    [$pj, , $team] = pjOfTeam();
    Team::factory()->create(['name' => 'Tim Lain']);
    [$member] = memberOfTeam();

    $this->actingAs($pj)->get(route('team-recap.overview'))
        ->assertOk()->assertInertia(fn ($page) => $page->has('teams', Team::count())->missing('canWriteNotes'));
    $this->actingAs($pj)->get(route('team-recap.export', ['period_type' => 'month', 'year' => 2026, 'month' => 11, 'scope' => 'all']))->assertOk()->assertJsonCount(Team::count(), 'teams');
    // Without scope=all the export keeps to the PJ's own team.
    $this->actingAs($pj)->get(route('team-recap.export', ['period_type' => 'month', 'year' => 2026, 'month' => 11]))->assertOk()->assertJsonCount(1, 'teams');
    $this->actingAs($pj)->get(route('profile.edit'))->assertInertia(fn ($page) => $page->where('can.review_bersama', true));

    $this->actingAs($member)->get(route('team-recap.overview'))->assertForbidden();
    $this->actingAs($member)->get(route('profile.edit'))->assertInertia(fn ($page) => $page->where('can.review_bersama', false));
});

it('summarises the meetings of a month per team: complete, partial, bukti rapat, and a week to come stays quiet', function () {
    Carbon\Carbon::setTestNow('2026-10-14 09:00:00');
    [$pj, $pjEmployee, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $other = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    recapItem($team, $pjEmployee, $plan, ['date_start' => '2026-10-05', 'date_end' => '2026-10-05']);
    recapItem($team, $pjEmployee, $plan, ['date_start' => '2026-10-12', 'date_end' => '2026-10-12']);
    recapItem($team, $pjEmployee, $other, ['date_start' => '2026-10-12', 'date_end' => '2026-10-12']);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => '2026-10-05', 'paraphrase' => 'Selesai']);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => '2026-10-12', 'paraphrase' => 'Berjalan']);
    TeamRecapEvidence::create(['team_id' => $team->id, 'period_type' => 'week', 'period_year' => 2026, 'week_start' => '2026-10-05', 'type' => 'notulen', 'title' => 'Notulen', 'url' => 'https://example.test/n', 'uploaded_by' => $pjEmployee->id]);

    $this->actingAs($pj)->get(route('team-recap.meetings', ['year' => 2026, 'month' => 10]))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Kinetik/MeetingSummary')
        ->where('teams.0.id', $team->id)
        ->where('teams.0.weeks.0.status', 'none')      // 28 Sep, in October's first row
        ->where('teams.0.weeks.1.status', 'done')      // every RK with kegiatan has a paraphrase
        ->where('teams.0.weeks.1.evidence', 1)
        ->where('teams.0.weeks.2.status', 'partial')   // one of two RK written
        ->where('teams.0.weeks.3.status', 'upcoming')
        ->where('teams.0.month.status', 'none'));

    [$member] = memberOfTeam();
    $this->actingAs($member)->get(route('team-recap.meetings'))->assertForbidden();
    Carbon\Carbon::setTestNow();
});

it('keeps the paraphrase text from everyone but the PJ, the RK PIC and admins', function () {
    [$memberUser, $member, $team] = memberOfTeam();
    $pj = Employee::factory()->create();
    $team->update(['leader_id' => $pj->id]);
    $pj->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'pic_employee_id' => null]);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => '2026-10-12', 'paraphrase' => 'Teks rahasia PJ', 'target' => 5]);
    $url = route('team-recap.weekly', ['team' => $team->id, 'week' => '2026-10-12']);
    $text = fn ($page) => json_encode($page->toArray()['props']['panels']);

    $this->actingAs($memberUser)->get($url)->assertInertia(fn ($page) => expect($text($page))->not->toContain('Teks rahasia PJ')->toContain('"target":5'));
    $this->actingAs(headUser())->get($url)->assertInertia(fn ($page) => expect($text($page))->not->toContain('Teks rahasia PJ'));
    $this->actingAs($pj->user ?? User::factory()->create(['id' => 9999]))->get($url);   // the PJ below
    $pjUser = staffUser();
    $pj->update(['user_id' => $pjUser->id]);
    $this->actingAs($pjUser)->get($url)->assertInertia(fn ($page) => expect($text($page))->toContain('Teks rahasia PJ'));
    $this->actingAs(adminUser())->get($url)->assertInertia(fn ($page) => expect($text($page))->toContain('Teks rahasia PJ'));
});

it('keeps Review Bersama monthly for the head, while the PJ and admins still choose the period', function () {
    $this->actingAs(headUser())->get(route('team-recap.overview', ['period_type' => 'week']))
        ->assertInertia(fn ($page) => $page->where('periodType', 'month')->where('monthlyOnly', true));

    [$pj] = pjOfTeam();
    $this->actingAs($pj)->get(route('team-recap.overview', ['period_type' => 'week']))
        ->assertInertia(fn ($page) => $page->where('periodType', 'week')->where('monthlyOnly', false));
});

// ── Meeting table: last week and this week, who did not plan, the PJ fills the permasalahan ──

it('gives the meeting last week and this week, lists who did not plan, and lets the PJ write the permasalahan', function () {
    [$pjUser, $pj, $team] = pjOfTeam();
    $member = Employee::factory()->create(['name' => 'Anggota Rajin']);
    $idle = Employee::factory()->create(['name' => 'Anggota Belum Rencana']);
    $team->members()->attach($member->id, ['role' => 'member', 'is_primary' => true]);
    $team->members()->attach($idle->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null]);

    $lastWeek = PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => 'Monitoring persiapan lapangan', 'date_start' => '2026-10-27', 'date_end' => '2026-10-28', 'target' => 4, 'target_unit' => 'kali', 'capaian' => 3, 'progress' => 75]);
    // A kegiatan of the whole month: listed in every week it runs, marked as continued after the first.
    PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => 'Pengembangan aplikasi sepanjang bulan', 'date_start' => '2026-10-01', 'date_end' => '2026-11-30']);

    $this->actingAs($pjUser)->get(route('team-recap.weekly', ['team' => $team->id, 'week' => '2026-11-02']))->assertInertia(fn ($page) => $page
        ->has('lastPlans', 2)->where('lastPlans.0.continued', true)
        ->has('plans', 1)->where('plans.0.continued', true)
        ->where('noPlan.last.0.name', fn ($name) => in_array($name, ['Anggota Belum Rencana', $idle->display_name ?? $idle->name], true))
        ->has('noPlan.this', 2));

    $this->actingAs($pjUser)->patch(route('plan-items.result', $lastWeek), ['capaian' => 4, 'progress' => 100, 'kendala' => 'Sinyal buruk di dua kecamatan'])->assertSessionHasNoErrors();
    expect($lastWeek->fresh()->kendala)->toBe('Sinyal buruk di dua kecamatan')->and((float) $lastWeek->fresh()->capaian)->toBe(4.0);

    // Another member, who is not the PJ, cannot write on someone else's kegiatan.
    $other = staffUser();
    Employee::factory()->create(['user_id' => $other->id]);
    $this->actingAs($other)->patch(route('plan-items.result', $lastWeek), ['kendala' => 'x'])->assertForbidden();
});

it('shows the PJ on the beranda who of their team still owes a plan or a realisasi', function () {
    [$pjUser, $pj, $team] = pjOfTeam();
    $done = Employee::factory()->create(['name' => 'Anggota Lengkap', 'display_name' => 'Anggota Lengkap']);
    $owes = Employee::factory()->create(['name' => 'Anggota Berutang', 'display_name' => 'Anggota Berutang']);
    $team->members()->attach([$done->id => ['role' => 'member', 'is_primary' => true], $owes->id => ['role' => 'member', 'is_primary' => true]]);
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null]);
    $monday = now(config('app.schedule_timezone', 'Asia/Makassar'))->startOfWeek();
    foreach ([[$done, $monday->copy()->subWeek(), 1], [$done, $monday->copy(), null], [$owes, $monday->copy()->subWeek(), null]] as [$who, $day, $capaian]) {
        PlanItem::create(['team_id' => $team->id, 'employee_id' => $who->id, 'performance_plan_id' => $rk->id, 'description' => 'Kegiatan '.$who->name, 'date_start' => $day->toDateString(), 'date_end' => $day->toDateString(), 'capaian' => $capaian]);
    }

    $this->actingAs($pjUser)->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->has('teamStatus', 1)->where('teamStatus.0.team_id', $team->id)
        // Whoever owes something comes first, the complete member last.
        ->where('teamStatus.0.members', function ($members) {
            $rows = collect($members);
            $owes = $rows->firstWhere('name', 'Anggota Berutang');

            return $owes['plan_count'] === 0 && $owes['last_filled'] === 0 && $owes['last_total'] === 1 && $rows->last()['name'] === 'Anggota Lengkap';
        })
        ->where('teamStatus.0.complete', 1));

    [$plain] = memberOfTeam();
    $this->actingAs($plain)->get(route('dashboard'))->assertInertia(fn ($page) => $page->missing('teamStatus'));
});

it('keeps the progress of each week of a kegiatan that runs for several weeks, and shows it in the meeting table and the member page', function () {
    [$pjUser, $pj, $team] = pjOfTeam();
    $member = Employee::factory()->create(['user_id' => staffUser()->id]);
    $team->members()->attach($member->id, ['role' => 'member', 'is_primary' => true]);
    $rk = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null]);
    $item = PlanItem::create(['team_id' => $team->id, 'employee_id' => $member->id, 'performance_plan_id' => $rk->id, 'description' => 'Monitoring tujuh kali sepanjang bulan', 'date_start' => '2026-11-02', 'date_end' => '2026-11-27', 'target' => 7, 'target_unit' => 'kali']);

    // The PJ records 2 for the first week and 5 for the second, in the meeting.
    $this->actingAs($pjUser)->patch(route('plan-items.result', $item), ['capaian' => 2, 'progress' => 30, 'kendala' => 'Sinyal', 'week' => '2026-11-02'])->assertSessionHasNoErrors();
    $this->actingAs($pjUser)->patch(route('plan-items.result', $item), ['capaian' => 5, 'progress' => 70, 'week' => '2026-11-09'])->assertSessionHasNoErrors();
    // Correcting the first week afterwards changes that week only: the kegiatan keeps the latest values.
    $this->actingAs($pjUser)->patch(route('plan-items.result', $item), ['capaian' => 3, 'progress' => 40, 'week' => '2026-11-02'])->assertSessionHasNoErrors();
    expect((float) $item->fresh()->capaian)->toBe(5.0)->and($item->fresh()->weekSnapshots()->count())->toBe(2);

    $week = fn (string $monday) => $this->actingAs($pjUser)->get(route('team-recap.weekly', ['team' => $team->id, 'week' => $monday]));
    $week('2026-11-02')->assertInertia(fn ($page) => $page->where('plans.0.capaian', 3)->where('plans.0.progress', 40)->where('plans.0.multi_week', true));
    $week('2026-11-09')->assertInertia(fn ($page) => $page->where('plans.0.capaian', 5)->where('plans.0.progress', 70));
    // A week with nothing recorded shows nothing yet; the last week shows the kegiatan's own latest values.
    $week('2026-11-16')->assertInertia(fn ($page) => $page->where('plans.0.capaian', null)->where('plans.0.progress', 0));
    $week('2026-11-23')->assertInertia(fn ($page) => $page->where('plans.0.capaian', 5));

    $this->actingAs($member->user)->get(route('weekly.index', ['week' => '2026-11-02']))->assertInertia(fn ($page) => $page->where('thisWeek.0.capaian', 3)->where('thisWeek.0.week', '2026-11-02'));
});
