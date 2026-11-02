<?php

use App\Actions\Kinetik\LinkPlansToProjectsAction;
use App\Kinetik\Data\KipRkData;
use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;

it('links an RK to the only Projek under its leader RK and lists candidates otherwise', function () {
    $team = Team::factory()->create();
    $sakernas = Project::factory()->create(['team_id' => $team->id, 'leader_rk' => 'Terlaksananya Dukungan Metodologi Kependudukan']);
    [$jaringan, $hardware] = Project::factory()->count(2)->create(['team_id' => $team->id, 'leader_rk' => 'Terlaksananya Pengelolaan TIK']);
    $other = Project::factory()->create(['team_id' => $team->id]);

    $single = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'leader_rk' => ' terlaksananya dukungan  metodologi kependudukan ']);
    $multi = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'leader_rk' => 'Terlaksananya Pengelolaan TIK']);
    $manual = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => $other->id, 'leader_rk' => 'Terlaksananya Dukungan Metodologi Kependudukan']);

    $action = new LinkPlansToProjectsAction;
    expect($action->execute())->toBe(1)
        ->and($single->fresh()->project_id)->toBe($sakernas->id)
        ->and($multi->fresh()->project_id)->toBeNull()
        ->and($manual->fresh()->project_id)->toBe($other->id);

    $candidates = $action->candidates(PerformancePlan::all());
    expect($candidates->get($multi->id))->toEqualCanonicalizing([$jaringan->id, $hardware->id])
        ->and($candidates->has($single->id))->toBeFalse();
});

it('stores the leader RK on local RK by kipApp id or by team and text', function () {
    $team = Team::factory()->create(['kip_external_id' => '106453']);
    $byId = PerformancePlan::factory()->create(['kip_external_id' => '111', 'team_id' => null, 'project_id' => null]);
    $byText = PerformancePlan::factory()->create(['kip_external_id' => '222', 'team_id' => $team->id, 'project_id' => null, 'description' => 'RK Anggota B']);

    $updated = (new LinkPlansToProjectsAction)->rememberLeaderRks(collect([
        KipRkData::fromApiRow(['rkid' => '111', 'rencanakinerja' => 'RK Anggota A', 'rencanakinerjaatasan' => 'RK Ketua A', 'timkerjaid' => '999']),
        KipRkData::fromApiRow(['rkid' => '333', 'rencanakinerja' => 'RK Anggota B', 'rencanakinerjaatasan' => 'RK Ketua B', 'timkerjaid' => '106453']),
    ]));

    expect($updated)->toBe(2)
        ->and($byId->fresh()->leader_rk)->toBe('RK Ketua A')
        ->and($byText->fresh()->leader_rk)->toBe('RK Ketua B');
});

it('narrows the candidates to the Projek the member works in, and learns the leader RK from the member\'s own RK', function () {
    $team = Team::factory()->create();
    [$mine, $other] = Project::factory()->count(2)->create(['team_id' => $team->id, 'leader_rk' => 'Terlaksananya Pengelolaan TIK']);
    $employee = Employee::factory()->create();
    $mine->members()->attach($employee->id, ['role' => 'member']);

    $known = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'leader_rk' => 'Terlaksananya Pengelolaan TIK']);
    $blank = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'leader_rk' => null, 'description' => 'Mengelola jaringan kantor']);
    EmployeeRk::create(['employee_id' => $employee->id, 'kip_rk_id' => '1', 'name' => 'Mengelola  JARINGAN kantor', 'leader_rk' => 'Terlaksananya Pengelolaan TIK', 'year' => (int) config('kinetik.kip.tahun')]);

    $candidates = (new LinkPlansToProjectsAction)->candidatesFor(PerformancePlan::all(), $employee);

    expect($candidates->get($known->id))->toBe([$mine->id])
        ->and($candidates->get($blank->id))->toBe([$mine->id]);

    $stranger = Employee::factory()->create();
    expect((new LinkPlansToProjectsAction)->candidatesFor(PerformancePlan::all(), $stranger)->get($known->id))->toEqualCanonicalizing([$mine->id, $other->id]);
});
