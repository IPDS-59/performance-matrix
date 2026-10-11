<?php

use App\Actions\Kinetik\SyncPerformanceAgreementAction;
use App\Kinetik\Sources\MockKipStructureSource;
use App\Models\Employee;
use App\Models\PerformanceIndicator;
use App\Models\Project;
use App\Models\Team;

it('gives each Projek the IKU of its RK Ketua Sasaran and drops the old RK-Ketua rows', function () {
    $leader = Employee::factory()->create(['kip_pegawai_id' => 'L1']);
    $team = Team::factory()->create(['leader_id' => $leader->id]);
    // Old sync stored the RK Ketua as an "IKU".
    $old = PerformanceIndicator::create(['team_id' => $team->id, 'year' => 2026, 'name' => 'RK Ketua Contoh', 'kip_external_id' => '294257']);
    $project = Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'RK Ketua Contoh', 'performance_indicator_id' => $old->id]);
    $unrelated = Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'RK lain']);

    $linked = (new SyncPerformanceAgreementAction)->syncTeam(app(MockKipStructureSource::class), $team);

    $iku = $project->fresh()->performanceIndicator;
    expect($linked)->toBe(1)
        ->and($iku->name)->toBe('Persentase Publikasi Statistik yang Berkualitas')
        ->and($iku->sasaran)->toBe('Terwujudnya Penyediaan Data Statistik')
        ->and((float) $iku->target)->toBe(100.0)
        ->and($iku->target_unit)->toBe('%')
        ->and($unrelated->fresh()->performance_indicator_id)->toBeNull()
        ->and(PerformanceIndicator::find($old->id))->toBeNull();

    // Running again reuses the same row.
    (new SyncPerformanceAgreementAction)->syncTeam(app(MockKipStructureSource::class), $team);
    expect(PerformanceIndicator::where('team_id', $team->id)->count())->toBe(1);
});

it('does nothing for a team whose PJ has no kipApp id', function () {
    $team = Team::factory()->create(['leader_id' => Employee::factory()->create(['kip_pegawai_id' => null])->id]);

    expect((new SyncPerformanceAgreementAction)->syncTeam(app(MockKipStructureSource::class), $team))->toBe(0);
});
