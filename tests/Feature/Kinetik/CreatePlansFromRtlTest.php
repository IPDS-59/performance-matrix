<?php

use App\Actions\Kinetik\CreatePlansFromRtlAction;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\RecapLock;
use App\Models\RecapOverride;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;

function rtlTeam(): array
{
    $pj = Employee::factory()->create(['user_id' => User::factory()->create()->id]);
    $team = Team::factory()->create(['leader_id' => $pj->id]);
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);
    RecapLock::create(['team_id' => $team->id, 'period_type' => 'quarter', 'period_year' => 2026, 'period_quarter' => 3]);

    return [$team, $pj, $rk];
}

function rtl(Team $team, PerformancePlan $rk, array $attrs = []): RecapOverride
{
    return RecapOverride::create(['team_id' => $team->id, 'performance_plan_id' => $rk->id, 'period_type' => 'quarter', 'period_year' => 2026, 'period_quarter' => 3, 'follow_up_plan' => 'Perbaiki entri blok 12', ...$attrs]);
}

it('turns each RTL of a locked quarter into a plan item once', function () {
    [$team, $pj, $rk] = rtlTeam();
    $pic = Employee::factory()->create(['user_id' => User::factory()->create()->id]);
    $withPic = rtl($team, $rk, ['follow_up_pic_employee_id' => $pic->id, 'follow_up_deadline' => '2026-11-15']);
    $noPic = rtl($team, $rk, ['follow_up_plan' => 'Susun SOP']);
    rtl($team, $rk, ['follow_up_plan' => '  ']); // empty RTL is skipped

    $now = Carbon::parse('2026-10-01 06:00');
    expect(app(CreatePlansFromRtlAction::class)->execute($now))->toBe(2);

    $a = PlanItem::where('source_ref', $withPic->id)->first();
    $b = PlanItem::where('source_ref', $noPic->id)->first();
    expect($a)->source->toBe('rtl')->employee_id->toBe($pic->id)->description->toBe('Perbaiki entri blok 12')
        ->and(substr($a->date_start, 0, 10))->toBe('2026-10-01')
        ->and(substr($a->date_end, 0, 10))->toBe('2026-11-15')
        ->and($b->employee_id)->toBe($pj->id)
        ->and(substr($b->date_end, 0, 10))->toBe('2026-12-31')
        ->and($pic->user->notifications)->toHaveCount(1);

    // Cancelled plans are not recreated, and a second run adds nothing.
    $a->update(['status' => 'cancelled']);
    expect(app(CreatePlansFromRtlAction::class)->execute($now))->toBe(0)
        ->and(PlanItem::count())->toBe(2);
});

it('ignores unlocked quarters and other quarters', function () {
    [$team, , $rk] = rtlTeam();
    RecapLock::query()->delete();
    rtl($team, $rk);

    expect(app(CreatePlansFromRtlAction::class)->execute(Carbon::parse('2026-10-01')))->toBe(0);

    [$team2, , $rk2] = rtlTeam();
    rtl($team2, $rk2);
    expect(app(CreatePlansFromRtlAction::class)->execute(Carbon::parse('2027-01-02')))->toBe(0); // looks at Q4 2026
});

it('starts a late-created plan today, not in the past', function () {
    [$team, , $rk] = rtlTeam();
    rtl($team, $rk, ['follow_up_deadline' => '2026-10-02']);

    app(CreatePlansFromRtlAction::class)->execute(Carbon::parse('2026-10-20 06:00'));

    $plan = PlanItem::sole();
    expect(substr($plan->date_start, 0, 10))->toBe('2026-10-20')->and(substr($plan->date_end, 0, 10))->toBe('2026-10-20');
});
