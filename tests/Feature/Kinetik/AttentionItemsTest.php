<?php

use App\Actions\Kinetik\BuildAttentionItemsAction;
use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\RkRecap;
use App\Models\Team;
use Illuminate\Support\Carbon;

beforeEach(fn () => Carbon::setTestNow('2026-10-14 09:00:00'));
afterEach(fn () => Carbon::setTestNow());

function attentionTeam(): array
{
    $team = Team::factory()->create();
    $ana = Employee::factory()->create(['display_name' => 'Ana', 'is_active' => true]);
    $budi = Employee::factory()->create(['display_name' => 'Budi', 'is_active' => true]);
    foreach ([$ana, $budi] as $e) {
        $e->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    }
    $rk = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id]);

    return [$team, $ana, $budi, $rk];
}

function attentionItem(Team $team, Employee $e, PerformancePlan $rk, array $extra = []): PlanItem
{
    return PlanItem::create(['team_id' => $team->id, 'employee_id' => $e->id, 'performance_plan_id' => $rk->id, 'description' => 'Entri data',
        'date_start' => '2026-10-13', 'date_end' => '2026-10-14', 'target' => 5, 'target_unit' => 'blok', ...$extra]);
}

function attentionGroups(Team $team): array
{
    return collect(app(BuildAttentionItemsAction::class)->execute(collect([$team])))->keyBy('key')->all();
}

it('lists members who have no plan this week, most urgent first', function () {
    [$team, $ana, , $rk] = attentionTeam();
    attentionItem($team, $ana, $rk);

    $groups = attentionGroups($team);

    expect($groups['no_plan']['count'])->toBe(1)
        ->and($groups['no_plan']['samples'])->toBe(['Budi'])
        ->and($groups['no_plan']['severity'])->toBe('high');
});

it('counts kegiatan without a target this month and ended kegiatan without a capaian', function () {
    [$team, $ana, $budi, $rk] = attentionTeam();
    attentionItem($team, $ana, $rk, ['target' => null]);
    attentionItem($team, $budi, $rk, ['date_start' => '2026-10-05', 'date_end' => '2026-10-06', 'capaian' => null]);
    attentionItem($team, $budi, $rk, ['date_start' => '2026-10-07', 'date_end' => '2026-10-08', 'capaian' => 3]);
    attentionItem($team, $ana, $rk, ['status' => 'cancelled', 'target' => null]);

    $groups = attentionGroups($team);

    expect($groups['missing_target']['count'])->toBe(1)
        ->and($groups['missing_target']['samples'][0])->toStartWith('Ana: ')
        ->and($groups['missing_capaian']['count'])->toBe(1);
});

it('flags failed pushes and a last week recap that is not locked', function () {
    [$team, $ana, , $rk] = attentionTeam();
    attentionItem($team, $ana, $rk, ['date_start' => '2026-10-07', 'date_end' => '2026-10-08', 'status' => 'planned', 'push_error' => 'SKP triwulan 4 belum dibuat']);

    $groups = attentionGroups($team);
    expect($groups['failed_push']['count'])->toBe(1)->and($groups['recap_open']['samples'])->toBe([$team->name]);

    RkRecap::create(['team_id' => $team->id, 'performance_plan_id' => $rk->id, 'period_type' => 'week', 'period_start' => '2026-10-05', 'paraphrase' => 'x', 'locked_at' => now()]);
    expect(attentionGroups($team))->not->toHaveKey('recap_open');
});

it('returns nothing for an empty scope and shows the panel on the overview for the head', function () {
    expect(app(BuildAttentionItemsAction::class)->execute(collect()))->toBe([]);

    [$team] = attentionTeam();
    $this->actingAs(headUser())->get(route('team-recap.overview'))->assertInertia(fn ($page) => $page->has('attention'));
});
