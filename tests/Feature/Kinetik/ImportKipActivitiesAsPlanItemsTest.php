<?php

use App\Actions\Kinetik\ImportKipActivitiesAsPlanItemsAction;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;

function importFixture(): array
{
    $team = Team::factory()->create();
    $employee = Employee::factory()->create(['team_id' => $team->id]);
    $plan = PerformancePlan::factory()->create(['team_id' => $team->id, 'kip_external_id' => '900', 'description' => 'RK satu']);

    return [$employee, $plan];
}

it('turns a synced kipApp kegiatan into a plan item once, with kipApp progres as status', function () {
    [$employee, $plan] = importFixture();
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => 'a1', 'rk_external_id' => '900', 'progress' => 100, 'achievement_note' => 'selesai', 'sent_at' => '2026-10-10']);
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => 'a2', 'rk_external_id' => '900', 'progress' => 40]);
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => 'a3', 'rk_external_id' => '900', 'progress' => null]);

    expect(app(ImportKipActivitiesAsPlanItemsAction::class)->execute())->toBe(3);

    $done = PlanItem::firstWhere('kip_external_id', 'a1');
    expect($done->status)->toBe('done')
        ->and($done->performance_plan_id)->toBe($plan->id)
        ->and($done->team_id)->toBe($employee->team_id)
        ->and($done->capaian_note)->toBe('selesai')
        ->and($done->kip_sent_at)->not->toBeNull()
        ->and(PlanItem::firstWhere('kip_external_id', 'a2')->status)->toBe('in_progress')
        ->and(PlanItem::firstWhere('kip_external_id', 'a3')->status)->toBe('pushed');

    expect(app(ImportKipActivitiesAsPlanItemsAction::class)->execute())->toBe(0);
});

it('skips a kegiatan without an RK or an employee team', function () {
    [$employee] = importFixture();
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => 'b1', 'rk_external_id' => null]);
    $loner = Employee::factory()->create(['team_id' => null]);
    KipActivity::factory()->create(['employee_id' => $loner->id, 'external_id' => 'b2', 'rk_external_id' => '777', 'rk_name' => 'tanpa tim']);

    expect(app(ImportKipActivitiesAsPlanItemsAction::class)->execute())->toBe(0);
});

it('shows the imported kegiatan on Rekap Mingguan of the member', function () {
    [$employee] = importFixture();
    $user = staffUser();
    $employee->update(['user_id' => $user->id]);
    $day = now()->startOfWeek()->addDay()->toDateString();
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => 'c1', 'rk_external_id' => '900', 'description' => 'Uraian dari kipApp', 'activity_date_start' => $day, 'activity_date_end' => $day, 'progress' => 100]);
    app(ImportKipActivitiesAsPlanItemsAction::class)->execute();

    $this->actingAs($user)->get(route('weekly.index', ['week' => $day]))->assertInertia(fn ($page) => $page
        ->where('thisWeek', fn ($rows) => collect($rows)->contains('description', 'Uraian dari kipApp')));
});
