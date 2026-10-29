<?php

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;

function importSetup(): array
{
    $team = Team::factory()->create(['name' => 'Tim Neraca']);
    $project = Project::factory()->create(['team_id' => $team->id, 'name' => 'Publikasi PDRB Lapangan Usaha', 'year' => (int) config('kinetik.kip.tahun')]);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => $team->id, 'description' => 'Menyusun publikasi PDRB', 'target_unit' => 'dokumen']);
    $member = Employee::factory()->create(['name' => 'Aditya Saputra, SST, M.URP', 'is_active' => true]);
    $member->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    return [$team, $project, $plan, $member];
}

function sheetRow(array $over = []): array
{
    return ['sheet' => 'Neraca Wilayah', 'member' => 'Aditya Saputra, SST', 'project' => 'Publikasi PDRB', 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'target' => 4, 'realization' => 3, 'percent' => 75, ...$over];
}

it('matches the member by name without titles, the projek loosely, and picks the only RK', function () {
    [, $project, $plan, $member] = importSetup();

    $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-28', 'rows' => [sheetRow()]])
        ->assertOk()
        ->assertJsonPath('rows.0.employee_id', $member->id)
        ->assertJsonPath('rows.0.project_id', $project->id)
        ->assertJsonPath('rows.0.performance_plan_id', $plan->id)
        ->assertJsonPath('rows.0.date_start', '2026-10-26')     // no date: the week's Monday ...
        ->assertJsonPath('rows.0.date_end', '2026-10-30')       // ... to Friday
        ->assertJsonPath('rows.0.progress', 75)
        ->assertJsonPath('rows.0.blocked', false);
});

it('flags unknown members, short uraian and rows that already exist', function () {
    [, , $plan, $member] = importSetup();
    PlanItem::create(['team_id' => $plan->team_id, 'employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-26', 'date_end' => '2026-10-30']);

    $rows = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [
        sheetRow(['member' => 'Orang Tidak Dikenal']),
        sheetRow(['description' => 'Pendek']),
        sheetRow(),
    ]])->json('rows');

    expect($rows[0]['blocked'])->toBeTrue()->and($rows[1]['blocked'])->toBeTrue()
        ->and($rows[2]['duplicate'])->toBeTrue();
});

it('saves the confirmed rows as planned kegiatan and skips duplicates', function () {
    [, $project, $plan, $member] = importSetup();
    $admin = adminUser();
    Employee::factory()->create(['user_id' => $admin->id]);
    $row = ['employee_id' => $member->id, 'performance_plan_id' => $plan->id, 'project_id' => $project->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-26', 'date_end' => '2026-10-30', 'target' => 4, 'capaian' => 3, 'progress' => 75];

    $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [$row]])->assertOk()->assertJsonPath('created', 1);
    expect(PlanItem::first())->source->toBe('import')->status->toBe('planned')->progress->toBe(75)->target_unit->toBe('dokumen');

    $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [$row]])->assertOk()->assertJsonPath('created', 0)->assertJsonPath('skipped.0.reason', 'Sudah ada di Kinetik.');
});

it('refuses an RK of another team', function () {
    [, , , $member] = importSetup();
    $admin = adminUser();
    Employee::factory()->create(['user_id' => $admin->id]);
    $other = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => Team::factory()->create()->id]);

    $this->actingAs($admin)->postJson(route('admin.import-kegiatan.store'), ['rows' => [[
        'employee_id' => $member->id, 'performance_plan_id' => $other->id, 'description' => 'Penyiapan kuesioner PDRB triwulanan', 'date_start' => '2026-10-26', 'date_end' => '2026-10-30',
    ]]])->assertJsonPath('created', 0);
});

it('keeps the import to admins', function () {
    $this->actingAs(staffUser())->get(route('admin.import-kegiatan'))->assertForbidden();
    $this->actingAs(headUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [sheetRow()]])->assertForbidden();
    $this->actingAs(adminUser())->get(route('admin.import-kegiatan'))->assertOk();
});

it('matches names the way Kinetik stores them, with the titles glued to the name', function () {
    [$team] = importSetup();
    $stored = Employee::factory()->create(['name' => 'Yudha Subakti S.Si.', 'display_name' => 'Yudha Subakti S.Si.', 'is_active' => true]);
    $stored->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);
    $muhammad = Employee::factory()->create(['name' => 'Muhammad Zein SST, M.P.W', 'is_active' => true]);
    $muhammad->teams()->attach($team->id, ['role' => 'member', 'is_primary' => true]);

    $rows = $this->actingAs(adminUser())->postJson(route('admin.import-kegiatan.preview'), ['week_start' => '2026-10-26', 'rows' => [
        sheetRow(['member' => 'Yudha Subakti, S.Si']),
        sheetRow(['member' => 'Dr. Muhammad Zein, SST, M.P.W.P']),
        sheetRow(['member' => 'Maya Novita Sari, S. Tr. Stat']),
    ]])->json('rows');

    expect($rows[0]['employee_id'])->toBe($stored->id)->and($rows[1]['employee_id'])->toBe($muhammad->id)->and($rows[2]['employee_id'])->toBeNull();
});
