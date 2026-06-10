<?php

use App\Actions\Kinetik\SyncKipStructureAction;
use App\Kinetik\Auth\ConfigBearerAuthenticator;
use App\Kinetik\Sources\ApiKipStructureSource;
use App\Models\Employee;
use App\Models\PerformanceIndicator;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'kinetik.kip.base_url' => 'https://kipapp.bps.go.id/api',
        'kinetik.kip.token' => 'test-token-abc',
        'kinetik.kip.timeout' => 15,
        'kinetik.kip.tahun' => 2026,
        // Most structure assertions don't care about logins; covered separately.
        'kinetik.kip.create_logins' => false,
    ]);
});

/**
 * Fake one team (106436) with one project (427670), led by 340013832,
 * with members 340013832 + 340053881; team members add 340017503.
 */
function fakeStructure(): void
{
    Http::fake([
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response([
            [
                'timkerjaid' => '106436',
                'namatim' => 'UMUM',
                'niplamaketua' => '340013832',
                'namaketua' => 'Imron',
                'rkketuaid' => '294257',
                'rencanakinerjaketua' => 'Pengelolaan SDM yang baik',
                'proyekid' => '427670',
                'namaproyek' => ' Pengembangan SDM ',
                'anggota' => [
                    ['anggotaid' => 'a1', 'niplama' => '340013832', 'nama' => 'Imron'],
                    ['anggotaid' => 'a2', 'niplama' => '340053881', 'nama' => 'Asmawati'],
                    ['anggotaid' => 'a3', 'niplama' => '999999999', 'nama' => 'Unknown'],
                ],
            ],
        ], 200),
        'kipapp.bps.go.id/api/v1/timkerja/anggota*' => Http::response([
            ['anggotaid' => 'm1', 'niplama' => '340013832', 'nama' => 'Imron'],
            ['anggotaid' => 'm2', 'niplama' => '340017503', 'nama' => 'Verawati'],
        ], 200),
    ]);
}

function runStructureSync(array $timkerjaIds = ['106436']): array
{
    return (new SyncKipStructureAction)->execute(
        new ApiKipStructureSource(new ConfigBearerAuthenticator),
        $timkerjaIds,
    );
}

it('creates a team and project keyed on kip external ids', function () {
    fakeStructure();
    Employee::factory()->create(['nip_lama' => '340013832', 'name' => 'Imron']);
    Employee::factory()->create(['nip_lama' => '340053881', 'name' => 'Asmawati']);

    $summary = runStructureSync();

    expect($summary['teams'])->toBe(1)
        ->and($summary['projects'])->toBe(1);

    $team = Team::where('kip_external_id', '106436')->first();
    expect($team)->not->toBeNull()
        ->and($team->name)->toBe('UMUM');

    $project = Project::where('kip_external_id', '427670')->first();
    expect($project)->not->toBeNull()
        ->and($project->name)->toBe('Pengembangan SDM')
        ->and($project->team_id)->toBe($team->id);
});

it('sets the team and project leader from niplamaketua', function () {
    fakeStructure();
    $leader = Employee::factory()->create(['nip_lama' => '340013832']);

    runStructureSync();

    $team = Team::where('kip_external_id', '106436')->first();
    $project = Project::where('kip_external_id', '427670')->first();

    expect($team->leader_id)->toBe($leader->id)
        ->and($project->leader_id)->toBe($leader->id)
        ->and($team->members()->wherePivot('role', 'leader')->pluck('employees.id')->all())->toContain($leader->id);
});

it('provisions employees from kipApp member rows and mirrors project members', function () {
    fakeStructure();
    Employee::factory()->create(['nip_lama' => '340013832', 'name' => 'Old Name']);

    $summary = runStructureSync();

    $project = Project::where('kip_external_id', '427670')->first();
    // 3 anggota in the fake (incl. previously-unknown 999999999) are all linked.
    expect($project->members()->count())->toBe(3)
        ->and(Employee::where('nip_lama', '999999999')->exists())->toBeTrue()
        ->and($summary['employees_created'])->toBeGreaterThan(0);

    // Existing employee is refreshed from kipApp, not duplicated.
    expect(Employee::where('nip_lama', '340013832')->count())->toBe(1)
        ->and(Employee::where('nip_lama', '340013832')->first()->name)->toBe('Imron');
});

it('creates login accounts with derived emails and default password', function () {
    config(['kinetik.kip.create_logins' => true, 'kinetik.kip.email_domain' => 'bpssulteng.id']);
    seedRolesAndPermissions();
    fakeStructure();

    $summary = runStructureSync();

    expect($summary['users_created'])->toBeGreaterThan(0);

    $imron = Employee::where('nip_lama', '340013832')->first();
    expect($imron->user_id)->not->toBeNull();

    $user = User::find($imron->user_id);
    expect($user->email)->toBe('imron@bpssulteng.id')
        ->and(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->hasRole('staff'))->toBeTrue();
});

it('syncs IKU from the project rkketua and links the project', function () {
    fakeStructure();

    $summary = runStructureSync();

    $iku = PerformanceIndicator::where('kip_external_id', '294257')->first();
    expect($iku)->not->toBeNull()
        ->and($iku->name)->toBe('Pengelolaan SDM yang baik')
        ->and($summary['indicators'])->toBe(1);

    $project = Project::where('kip_external_id', '427670')->first();
    expect($project->performance_indicator_id)->toBe($iku->id);
});

it('skips member rows without a niplama', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response([[
            'timkerjaid' => '1', 'namatim' => 'T', 'proyekid' => 'p1', 'namaproyek' => 'P',
            'anggota' => [
                ['anggotaid' => 'a1', 'niplama' => '340000001', 'nama' => 'Ada'],
                ['anggotaid' => 'a2', 'niplama' => '', 'nama' => 'No NIP'],
            ],
        ]], 200),
        'kipapp.bps.go.id/api/v1/timkerja/anggota*' => Http::response([], 200),
    ]);

    $summary = runStructureSync(['1']);

    expect($summary['skipped_no_niplama'])->toBe(1)
        ->and($summary['employees_created'])->toBe(1);
});

it('makes project members and roster team members so recaps resolve', function () {
    fakeStructure();
    $leader = Employee::factory()->create(['nip_lama' => '340013832']);

    runStructureSync();

    $team = Team::where('kip_external_id', '106436')->first();

    // leader (340013832) + project members (340053881, 999999999) + roster (340017503)
    expect($team->members()->count())->toBe(4)
        ->and($team->members()->wherePivot('role', 'leader')->pluck('employees.id')->all())->toBe([$leader->id]);

    // Project member with no team roster entry still resolves a team membership.
    $projectMember = Employee::where('nip_lama', '340053881')->first();
    expect($projectMember->teams()->where('teams.id', $team->id)->exists())->toBeTrue()
        ->and($projectMember->team_id)->toBe($team->id); // home team set
});

it('adopts an existing same-named team without a kip id', function () {
    fakeStructure();
    $existing = Team::create(['name' => 'UMUM', 'code' => 'UMM', 'is_active' => true]);

    runStructureSync();

    expect(Team::where('name', 'UMUM')->count())->toBe(1);
    expect($existing->fresh()->kip_external_id)->toBe('106436');
});

it('is idempotent across repeated runs', function () {
    fakeStructure();
    Employee::factory()->create(['nip_lama' => '340013832']);
    Employee::factory()->create(['nip_lama' => '340053881']);

    runStructureSync();
    runStructureSync();

    expect(Team::where('kip_external_id', '106436')->count())->toBe(1)
        ->and(Project::where('kip_external_id', '427670')->count())->toBe(1);

    $project = Project::where('kip_external_id', '427670')->first();
    // 3 anggota mirrored, no duplicate employees across runs.
    expect($project->members()->count())->toBe(3)
        ->and(Employee::where('nip_lama', '340013832')->count())->toBe(1);
});
