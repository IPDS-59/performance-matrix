<?php

use App\Actions\Kinetik\RemindWeeklyPlansAction;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Models\User;
use App\Services\Kinetik\PlanEvaluator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

function teamWithMembers(): array
{
    $pjUser = User::factory()->create();
    $pj = Employee::factory()->create(['user_id' => $pjUser->id, 'is_active' => true, 'name' => 'Sukma']);
    $team = Team::factory()->create(['leader_id' => $pj->id, 'name' => 'Tim MTI']);
    $pj->teams()->attach($team->id, ['role' => 'leader', 'is_primary' => true]);

    $members = collect(['Ani', 'Budi'])->map(function (string $name) use ($team) {
        $user = User::factory()->create();
        $employee = Employee::factory()->create(['user_id' => $user->id, 'is_active' => true, 'name' => $name, 'display_name' => $name]);
        $employee->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

        return [$employee, $user];
    });

    return [$pjUser, $pj, $team, $members];
}

it('reminds members without a plan and sends the PJ one summary, once per week', function () {
    Cache::flush();
    [$pjUser, $pj, $team, $members] = teamWithMembers();
    [[$ani, $aniUser], [$budi, $budiUser]] = $members->all();
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    PlanItem::create(['team_id' => $team->id, 'employee_id' => $budi->id, 'performance_plan_id' => $rk->id, 'description' => 'x', 'date_start' => '2026-10-05', 'date_end' => '2026-10-06']);

    $monday = Carbon::parse('2026-10-05 09:00');
    $sent = app(RemindWeeklyPlansAction::class)->execute($monday);

    expect($sent)->toBe(['members' => 2, 'pj' => 1])
        ->and($aniUser->notifications)->toHaveCount(1)
        ->and($budiUser->notifications)->toHaveCount(0)
        ->and($pjUser->notifications->pluck('data.type')->sort()->values()->all())->toBe(['plan_reminder', 'plan_reminder_pj'])
        ->and($pjUser->notifications->firstWhere('data.type', 'plan_reminder_pj')->data['message'])->toContain('Ani')->not->toContain('Budi');

    // A second run in the same week sends nothing.
    expect(app(RemindWeeklyPlansAction::class)->execute($monday))->toBe(['members' => 0, 'pj' => 0]);
});

it('does not remind inactive members or members of no team', function () {
    Cache::flush();
    [, , , $members] = teamWithMembers();
    [$ani, $aniUser] = $members->first();
    $ani->update(['is_active' => false]);
    $loner = User::factory()->create();
    Employee::factory()->create(['user_id' => $loner->id, 'is_active' => true]);

    app(RemindWeeklyPlansAction::class)->execute(Carbon::parse('2026-10-05 09:00'));

    expect($aniUser->notifications)->toHaveCount(0)
        ->and($loner->notifications)->toHaveCount(0);
});

function evalPlan(?string $override = null): PlanItem
{
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'kip_external_id' => '777', 'description' => 'RK Inovasi']);
    $plan = PlanItem::create([
        'team_id' => Team::factory()->create()->id, 'employee_id' => Employee::factory()->create()->id, 'performance_plan_id' => $rk->id,
        'description' => 'Entri', 'date_start' => '2026-10-05', 'date_end' => '2026-10-09',
        'override_status' => $override, 'override_reason' => $override ? 'PJ bilang selesai' : null,
    ]);

    return $plan->load('performancePlan');
}

function activityOf(PlanItem $plan, array $attrs): KipActivity
{
    return KipActivity::factory()->create(['employee_id' => $plan->employee_id, 'rk_external_id' => '777', 'activity_date_start' => '2026-10-06', 'activity_date_end' => '2026-10-06', ...$attrs]);
}

it('evaluates a plan from the matching kipApp kegiatan', function () {
    $evaluator = new PlanEvaluator;
    $plan = evalPlan();

    expect($evaluator->evaluate($plan, collect())['state'])->toBe('not_started');

    $done = activityOf($plan, ['progress' => 100]);
    $running = activityOf($plan, ['progress' => 40]);
    $elsewhere = activityOf($plan, ['rk_external_id' => '999', 'rk_name' => 'RK lain', 'progress' => 100]);
    $outside = activityOf($plan, ['activity_date_start' => '2026-10-20', 'activity_date_end' => '2026-10-20', 'progress' => 100]);

    $mixed = $evaluator->evaluate($plan, collect([$done, $running, $elsewhere, $outside]));
    expect($mixed)->state->toBe('in_progress')->activity_count->toBe(2)->progress->toBe(70.0);

    expect($evaluator->evaluate($plan, collect([$done, $elsewhere]))['state'])->toBe('done');
});

it('lets a PJ override the computed result with a reason', function () {
    $plan = evalPlan('done');

    $result = (new PlanEvaluator)->evaluate($plan, collect());

    expect($result)->state->toBe('done')->overridden->toBeTrue()->override_reason->toBe('PJ bilang selesai')->computed_state->toBe('not_started');
});

it('stores and clears a PJ correction through the endpoint, PJ only', function () {
    [$pjUser, $pj, $team, $members] = teamWithMembers();
    [[$ani, $aniUser]] = $members->all();
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    $plan = PlanItem::create(['team_id' => $team->id, 'employee_id' => $ani->id, 'performance_plan_id' => $rk->id, 'description' => 'x', 'date_start' => '2026-10-05', 'date_end' => '2026-10-06']);

    $this->actingAs($aniUser)->patch(route('plan-items.evaluate', $plan), ['status' => 'done', 'reason' => 'ok'])->assertForbidden();

    $this->actingAs($pjUser)->patch(route('plan-items.evaluate', $plan), ['status' => 'done'])->assertSessionHasErrors('reason');
    $this->actingAs($pjUser)->patch(route('plan-items.evaluate', $plan), ['status' => 'done', 'reason' => 'Dikerjakan di luar kipApp'])->assertSessionHas('success');
    expect($plan->fresh())->override_status->toBe('done')->override_reason->toBe('Dikerjakan di luar kipApp')->override_by->toBe($pj->id)
        ->and($aniUser->notifications)->toHaveCount(1);

    $this->actingAs($pjUser)->patch(route('plan-items.evaluate', $plan), ['status' => ''])->assertSessionHas('success');
    expect($plan->fresh()->override_status)->toBeNull();
});
