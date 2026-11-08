<?php

use App\Actions\Kinetik\ImportKipActivitiesAsPlanItemsAction;
use App\Models\Employee;
use App\Models\KipActivity;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
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

it('joins a synced kipApp kegiatan to an imported one of the same person, day and words instead of making a second', function () {
    [$employee, $plan] = importFixture();
    $imported = PlanItem::create(['team_id' => $employee->team_id, 'employee_id' => $employee->id, 'performance_plan_id' => $plan->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-15', 'date_end' => '2026-10-15', 'source' => 'import', 'status' => 'planned', 'target' => 4, 'capaian' => 3]);
    $other = PlanItem::create(['team_id' => $employee->team_id, 'employee_id' => $employee->id, 'performance_plan_id' => $plan->id, 'description' => 'Mengikuti BRS 2', 'date_start' => '2026-10-15', 'date_end' => '2026-10-15', 'source' => 'import', 'status' => 'planned']);
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => 'm1', 'rk_external_id' => '900', 'description' => 'Menyiapkan kuesioner PDRB triwulanan', 'activity_date_start' => '2026-10-15', 'progress' => 100, 'achievement_note' => 'selesai']);
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => 'm2', 'rk_external_id' => '900', 'description' => 'Mengikuti BRS 5', 'activity_date_start' => '2026-10-15', 'progress' => 100]);

    $created = app(ImportKipActivitiesAsPlanItemsAction::class)->execute();

    expect($created)->toBe(1)                                    // only BRS 5 is new
        ->and($imported->fresh())->kip_external_id->toBe('m1')->status->toBe('done')->source->toBe('import')
        ->and((float) $imported->fresh()->target)->toBe(4.0)     // what the admin imported stays
        ->and($imported->fresh()->capaian_note)->toBe('selesai')
        ->and($other->fresh()->kip_external_id)->toBeNull()      // BRS 2 is not BRS 5
        ->and(PlanItem::where('employee_id', $employee->id)->count())->toBe(3);
});

it('gives a kegiatan from kipApp the projek of its RK', function () {
    [$employee, $plan] = importFixture();
    $project = Project::factory()->create(['team_id' => $plan->team_id]);
    $plan->update(['project_id' => $project->id]);
    KipActivity::factory()->create(['employee_id' => $employee->id, 'external_id' => 'p1', 'rk_external_id' => '900']);

    app(ImportKipActivitiesAsPlanItemsAction::class)->execute();

    expect(PlanItem::firstWhere('kip_external_id', 'p1')->project_id)->toBe($project->id);
});

it('gives a kegiatan under a team leader\'s RK Ketua the projek, only when that RK Ketua heads one projek of theirs', function () {
    $team = Team::factory()->create();
    $leader = Employee::factory()->create(['team_id' => $team->id]);
    $zi = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'kip_external_id' => '901', 'description' => 'Terlaksananya Keberhasilan Pembangunan Zona Integritas']);
    $sosial = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'kip_external_id' => '902', 'description' => 'Tersedianya Laporan Statistik Ketahanan Sosial']);
    $ziProject = Project::factory()->create(['team_id' => $team->id, 'leader_id' => $leader->id, 'leader_rk' => 'Terlaksananya  keberhasilan Pembangunan Zona Integritas ']);
    Project::factory()->count(2)->create(['team_id' => $team->id, 'leader_id' => $leader->id, 'leader_rk' => 'Tersedianya Laporan Statistik Ketahanan Sosial']);
    KipActivity::factory()->create(['employee_id' => $leader->id, 'external_id' => 'k1', 'rk_external_id' => '901']);
    KipActivity::factory()->create(['employee_id' => $leader->id, 'external_id' => 'k2', 'rk_external_id' => '902']);

    app(ImportKipActivitiesAsPlanItemsAction::class)->execute();

    expect(PlanItem::firstWhere('kip_external_id', 'k1')->project_id)->toBe($ziProject->id)
        ->and(PlanItem::firstWhere('kip_external_id', 'k2')->project_id)->toBeNull();
});

it('fills the projek of kegiatan from kipApp that came in without one, sent ones too', function () {
    [$employee, $plan] = importFixture();
    $project = Project::factory()->create(['team_id' => $plan->team_id]);
    $plan->update(['project_id' => $project->id]);
    $sent = PlanItem::create(['team_id' => $employee->team_id, 'employee_id' => $employee->id, 'performance_plan_id' => $plan->id, 'description' => 'Pataba BPS Prov Sulteng', 'date_start' => '2026-09-28', 'date_end' => '2026-09-28', 'source' => 'kipapp', 'status' => 'done', 'kip_external_id' => 'old1', 'kip_sent_at' => '2026-09-29']);
    $chosen = PlanItem::create(['team_id' => $employee->team_id, 'employee_id' => $employee->id, 'performance_plan_id' => $plan->id, 'description' => 'Dibuat di Kinetik tanpa projek', 'date_start' => '2026-09-28', 'date_end' => '2026-09-28', 'source' => 'member', 'status' => 'planned']);

    app(ImportKipActivitiesAsPlanItemsAction::class)->execute();

    expect($sent->fresh()->project_id)->toBe($project->id)
        ->and($chosen->fresh()->project_id)->toBeNull();
});
