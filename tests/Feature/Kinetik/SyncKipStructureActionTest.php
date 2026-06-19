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
    // Isolate from the real username map so derived-email logic is exercised.
    $emptyMap = writeTempUsernameMap([]);
    config([
        'kinetik.kip.create_logins' => true,
        'kinetik.kip.email_domain' => 'bpssulteng.id',
        'kinetik.kip.username_map_path' => $emptyMap,
    ]);
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

it('deduplicates IKU by (team_id, name) when multiple projects share the same rencanakinerjaketua', function () {
    // Two projects, same team, same IKU name but DIFFERENT rkketuaids.
    Http::fake([
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response([
            [
                'timkerjaid' => '106436',
                'namatim' => 'UMUM',
                'niplamaketua' => '340013832',
                'namaketua' => 'Imron',
                'rkketuaid' => '294257',
                'rencanakinerjaketua' => 'Pengelolaan Manajemen Risiko yang baik',
                'proyekid' => '427670',
                'namaproyek' => 'Proyek A',
                'anggota' => [['anggotaid' => 'a1', 'niplama' => '340013832', 'nama' => 'Imron']],
            ],
            [
                'timkerjaid' => '106436',
                'namatim' => 'UMUM',
                'niplamaketua' => '340013832',
                'namaketua' => 'Imron',
                'rkketuaid' => '294999', // different rkketuaid, same name
                'rencanakinerjaketua' => 'Pengelolaan Manajemen Risiko yang baik',
                'proyekid' => '427671',
                'namaproyek' => 'Proyek B',
                'anggota' => [['anggotaid' => 'a2', 'niplama' => '340053881', 'nama' => 'Asmawati']],
            ],
        ], 200),
        'kipapp.bps.go.id/api/v1/timkerja/anggota*' => Http::response([], 200),
    ]);

    $summary = runStructureSync();

    $team = Team::where('kip_external_id', '106436')->first();
    // Both projects share the same IKU name → exactly 1 indicator for this team.
    expect(PerformanceIndicator::where('team_id', $team->id)->count())->toBe(1)
        ->and($summary['indicators'])->toBe(1);

    $iku = PerformanceIndicator::where('team_id', $team->id)->first();
    // kip_external_id is set to the first rkketuaid seen.
    expect($iku->kip_external_id)->toBe('294257');

    // Both projects link to the same indicator.
    $projectA = Project::where('kip_external_id', '427670')->first();
    $projectB = Project::where('kip_external_id', '427671')->first();
    expect($projectA->performance_indicator_id)->toBe($iku->id)
        ->and($projectB->performance_indicator_id)->toBe($iku->id);
});

// ---------------------------------------------------------------------------
// Real-email (username map) tests
// ---------------------------------------------------------------------------

/**
 * Write a temp fixture with the given niplama => username pairs and return the path.
 *
 * @param  array<string, string>  $map
 */
function writeTempUsernameMap(array $map): string
{
    $path = tempnam(sys_get_temp_dir(), 'kip_usernames_').'.json';
    file_put_contents($path, json_encode($map));

    return $path;
}

it('creates a login with the real SSO email when niplama is in the username map', function () {
    $mapPath = writeTempUsernameMap(['340013832' => 'imron.santoso', '340053881' => 'asmawati']);
    config([
        'kinetik.kip.create_logins' => true,
        'kinetik.kip.email_domain' => 'bpssulteng.id',
        'kinetik.kip.real_email_domain' => 'bps.go.id',
        'kinetik.kip.username_map_path' => $mapPath,
    ]);
    seedRolesAndPermissions();
    fakeStructure();

    runStructureSync();

    $imron = Employee::where('nip_lama', '340013832')->first();
    expect($imron->user_id)->not->toBeNull();
    expect(User::find($imron->user_id)->email)->toBe('imron.santoso@bps.go.id');
});

it('falls back to derived email when niplama is not in the username map', function () {
    $mapPath = writeTempUsernameMap([]); // empty map — no real email for anyone
    config([
        'kinetik.kip.create_logins' => true,
        'kinetik.kip.email_domain' => 'bpssulteng.id',
        'kinetik.kip.real_email_domain' => 'bps.go.id',
        'kinetik.kip.username_map_path' => $mapPath,
    ]);
    seedRolesAndPermissions();
    fakeStructure();

    runStructureSync();

    $verawati = Employee::where('nip_lama', '340017503')->first();
    expect($verawati->user_id)->not->toBeNull();
    expect(User::find($verawati->user_id)->email)->toBe('verawati@bpssulteng.id');
});

it('upgrades an existing derived-email login to the real email on re-sync', function () {
    $mapPath = writeTempUsernameMap(['340013832' => 'imron.santoso']);
    config([
        'kinetik.kip.create_logins' => true,
        'kinetik.kip.email_domain' => 'bpssulteng.id',
        'kinetik.kip.real_email_domain' => 'bps.go.id',
        'kinetik.kip.username_map_path' => $mapPath,
    ]);
    seedRolesAndPermissions();

    // Pre-create employee with a derived-email login (as if synced before the map existed).
    $existingUser = User::create([
        'name' => 'Imron',
        'email' => 'imron@bpssulteng.id',
        'password' => bcrypt('password'),
        'role' => 'staff',
    ]);
    $employee = Employee::factory()->create([
        'nip_lama' => '340013832',
        'name' => 'Imron',
        'user_id' => $existingUser->id,
    ]);

    fakeStructure();
    runStructureSync();

    expect($existingUser->fresh()->email)->toBe('imron.santoso@bps.go.id');
    // user_id remains the same user
    expect($employee->fresh()->user_id)->toBe($existingUser->id);
});

it('falls back to derived email when the real email is already taken by another user', function () {
    $mapPath = writeTempUsernameMap(['340053881' => 'asmawati']);
    config([
        'kinetik.kip.create_logins' => true,
        'kinetik.kip.email_domain' => 'bpssulteng.id',
        'kinetik.kip.real_email_domain' => 'bps.go.id',
        'kinetik.kip.username_map_path' => $mapPath,
    ]);
    seedRolesAndPermissions();

    // Another user already owns the real email.
    User::create([
        'name' => 'Other',
        'email' => 'asmawati@bps.go.id',
        'password' => bcrypt('password'),
        'role' => 'staff',
    ]);

    fakeStructure();
    runStructureSync();

    $asmawati = Employee::where('nip_lama', '340053881')->first();
    expect($asmawati->user_id)->not->toBeNull();

    $user = User::find($asmawati->user_id);
    // Must NOT be the real email (collision), must be the derived one instead.
    expect($user->email)->not->toBe('asmawati@bps.go.id')
        ->and($user->email)->toContain('@bpssulteng.id');
    // No exception, no duplicate email in users table.
    expect(User::where('email', 'asmawati@bps.go.id')->count())->toBe(1);
});

it('provisions the team leader when niplamaketua is not in the project roster', function () {
    // The leader (340099999) is NOT listed in anggota or team roster.
    Http::fake([
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response([
            [
                'timkerjaid' => '106436',
                'namatim' => 'UMUM',
                'niplamaketua' => '340099999',
                'namaketua' => 'Ketua Baru',
                'rkketuaid' => null,
                'rencanakinerjaketua' => null,
                'proyekid' => '427670',
                'namaproyek' => 'Proyek A',
                'anggota' => [
                    ['anggotaid' => 'a1', 'niplama' => '340013832', 'nama' => 'Imron'],
                ],
            ],
        ], 200),
        'kipapp.bps.go.id/api/v1/timkerja/anggota*' => Http::response([
            ['anggotaid' => 'm1', 'niplama' => '340013832', 'nama' => 'Imron'],
        ], 200),
    ]);

    runStructureSync();

    // Leader was provisioned even though not in any roster.
    expect(Employee::where('nip_lama', '340099999')->exists())->toBeTrue();

    $team = Team::where('kip_external_id', '106436')->first();
    $leader = Employee::where('nip_lama', '340099999')->first();

    expect($team->leader_id)->toBe($leader->id)
        ->and($team->members()->wherePivot('role', 'leader')->pluck('employees.id')->all())->toContain($leader->id);
});
