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

it('locks an RK with Simpan dan Kunci, blocks edits, and opens it again', function () {
    [$user, $pj, $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Final', 'lock' => true]))->assertSessionHas('success');
    expect(RkRecap::sole())->locked_at->not->toBeNull()->locked_by->toBe($pj->id);

    $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Ubah']))->assertSessionHas('error');
    expect(RkRecap::sole()->paraphrase)->toBe('Final');

    $this->actingAs($user)->post(route('team-recap.rk.unlock'), rkPayload($team, $plan))->assertSessionHas('success');
    expect(RkRecap::sole()->isLocked())->toBeFalse();
    $this->actingAs($user)->post(route('team-recap.rk.store'), rkPayload($team, $plan, ['paraphrase' => 'Ubah']))->assertSessionHas('success');
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
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'month', 'period_start' => '2026-09-01', 'paraphrase' => 'Ringkasan', 'locked_at' => now()]);

    $this->actingAs($user)->get(route('team-recap.monthly', ['year' => 2026]))->assertInertia(fn ($page) => $page
        ->where('view', 'list')
        ->where('panels', [])
        ->where('months.8.label', 'September')->where('months.8.activities', 2)->where('months.8.written', 1)->where('months.8.locked', 1)
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

it('opens Review Bersama to a PJ with only their teams, and keeps it closed to a plain member', function () {
    [$pj, , $team] = pjOfTeam();
    Team::factory()->create(['name' => 'Tim Lain']);
    [$member] = memberOfTeam();

    $this->actingAs($pj)->get(route('team-recap.overview'))
        ->assertOk()->assertInertia(fn ($page) => $page->has('teams', 1)->where('teams.0.id', $team->id)->missing('canWriteNotes'));
    $this->actingAs($pj)->get(route('profile.edit'))->assertInertia(fn ($page) => $page->where('can.review_bersama', true));

    $this->actingAs($member)->get(route('team-recap.overview'))->assertForbidden();
    $this->actingAs($member)->get(route('profile.edit'))->assertInertia(fn ($page) => $page->where('can.review_bersama', false));
});

it('summarises the meetings of a month per team: written, locked, bukti rapat, and a week to come stays quiet', function () {
    Carbon\Carbon::setTestNow('2026-10-14 09:00:00');
    [$pj, , $team] = pjOfTeam();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => '2026-10-05', 'paraphrase' => 'Selesai']);
    RecapLock::create(['team_id' => $team->id, 'period_type' => 'week', 'period_year' => 2026, 'week_start' => '2026-10-05']);
    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $plan->id, 'period_type' => 'week', 'period_start' => '2026-10-12', 'paraphrase' => 'Berjalan']);
    TeamRecapEvidence::create(['team_id' => $team->id, 'period_type' => 'week', 'period_year' => 2026, 'week_start' => '2026-10-05', 'type' => 'notulen', 'title' => 'Notulen', 'url' => 'https://example.test/n', 'uploaded_by' => $pj->employee->id]);

    $this->actingAs($pj)->get(route('team-recap.meetings', ['year' => 2026, 'month' => 10]))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Kinetik/MeetingSummary')
        ->where('teams.0.id', $team->id)
        ->where('teams.0.weeks.0.status', 'none')      // 28 Sep, in October's first row
        ->where('teams.0.weeks.1.status', 'locked')
        ->where('teams.0.weeks.1.evidence', 1)
        ->where('teams.0.weeks.2.status', 'written')
        ->where('teams.0.weeks.3.status', 'upcoming')
        ->where('teams.0.month.status', 'none'));

    [$member] = memberOfTeam();
    $this->actingAs($member)->get(route('team-recap.meetings'))->assertForbidden();
    Carbon\Carbon::setTestNow();
});
