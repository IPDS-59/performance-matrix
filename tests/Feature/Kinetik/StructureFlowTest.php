<?php

use App\Models\Employee;
use App\Models\MemberKipCredential;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRolesAndPermissions();
    config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'kinetik.kip.source' => 'api', 'kinetik.kip.structure_write' => true, 'kinetik.kip.periode_id' => 8]);
});

/** A tim led by a PJ with a stored kipApp login, one member, and a project of the tim. */
function structureSetup(): array
{
    $leaderUser = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    $leader = Employee::factory()->create(['user_id' => $leaderUser->id, 'kip_pegawai_id' => '501']);
    MemberKipCredential::remember($leaderUser, 'sso-pass', 'pj-token', now()->addHours(5));
    $member = Employee::factory()->create(['kip_pegawai_id' => '502']);
    $team = Team::factory()->create(['leader_id' => $leader->id, 'kip_external_id' => '3001']);
    $project = Project::create(['team_id' => $team->id, 'name' => 'Survei Contoh', 'year' => 2026, 'status' => 'active', 'leader_id' => $leader->id]);
    $project->members()->sync([$leader->id => ['role' => 'leader'], $member->id => ['role' => 'member']]);

    return [$leaderUser, $team, $project, $member];
}

/** A fake kipApp where each write makes the matching list show the new row. */
function fakeStructureWrites(bool $proyekExists = false): Closure
{
    Http::swap(new Factory);
    $state = ['writes' => [], 'rkKetua' => false, 'proyek' => $proyekExists, 'rkProyek' => false];
    Http::fake(function ($request) use (&$state) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if ($request->method() === 'POST') {
            $state['writes'][] = [$path, $request->data(), $request->header('x-auth')[0] ?? null];
            match ($path) {
                '/api/v1/rk/ketua' => $state['rkKetua'] = true,
                '/api/v1/proyek' => $state['proyek'] = true,
                '/api/v1/rk/proyek' => $state['rkProyek'] = true,
                default => null,
            };

            return Http::response(['status' => true]);
        }

        return match ($path) {
            '/api/v1/rk/ketua' => Http::response($state['rkKetua'] ? [['rkketuaid' => 77, 'rencanakinerjaketua' => 'Statistik sosial berkualitas']] : []),
            '/api/v1/proyek' => Http::response(str_contains($request->url(), 'leveltim=1')
                ? [['proyekid' => 9, 'namaproyek' => 'IKI JPT satu']]
                : ($state['proyek'] ? [['proyekid' => 88, 'namaproyek' => 'Survei Contoh', 'rkketuaid' => 77, 'anggota' => [['pegawaiid' => 501, 'anggotaid' => 1001], ['pegawaiid' => 502, 'anggotaid' => 1002]]]] : [])),
            '/api/v1/rk/proyek' => Http::response($state['rkProyek'] ? [['rkproyekid' => 555, 'rencanakinerja' => 'Entri data', 'anggotaid' => 1002]] : []),
            default => Http::response([], 404),
        };
    });

    return function () use (&$state) {
        return $state['writes'];
    };
}

it('is off while the flag is off', function () {
    config(['kinetik.kip.structure_write' => false]);
    [$user, $team, $project] = structureSetup();

    $this->actingAs($user)->getJson(route('kip.structure.options', ['team_id' => $team->id]))->assertNotFound();
    $this->actingAs($user)->post(route('projects.push', $project), ['rk_ketua_id' => '77'])->assertNotFound();
});

it('creates an RK Ketua with the keys kipApp uses and finds its id', function () {
    [$user, $team] = structureSetup();
    $writes = fakeStructureWrites();

    $this->actingAs($user)->post(route('kip.rk-ketua.store'), ['team_id' => $team->id, 'text' => 'Statistik sosial berkualitas', 'iki_jpt_id' => '9'])->assertSessionHas('success');

    $post = collect($writes())->firstWhere(0, '/api/v1/rk/ketua');
    expect($post[1])->toBe(['timkerjaid' => '3001', 'rencanakinerja' => 'Statistik sosial berkualitas', 'proyeklevel1id' => '9', 'pegawaiid' => '501'])
        ->and($post[2])->toBe('Bearer pj-token');
});

it('only lets the tim leader or a manager use the RK Ketua form', function () {
    [, $team] = structureSetup();
    $other = User::factory()->create();
    Employee::factory()->create(['user_id' => $other->id]);
    fakeStructureWrites();

    $this->actingAs($other)->getJson(route('kip.structure.options', ['team_id' => $team->id]))->assertForbidden();
});

it('creates the project in kipApp under an RK Ketua with the tim members and stores the proyekid', function () {
    [$user, , $project] = structureSetup();
    $writes = fakeStructureWrites();

    $this->actingAs($user)->post(route('projects.push', $project), ['rk_ketua_id' => '77'])->assertSessionHas('success');

    $post = collect($writes())->firstWhere(0, '/api/v1/proyek');
    expect($post[1])->toBe(['nama' => 'Survei Contoh', 'rkketuaid' => '77', 'anggota' => [['pegawaiid' => '501', 'isadmin' => 0], ['pegawaiid' => '502', 'isadmin' => 0]], 'head' => ['nama' => 'Survei Contoh']]);
    expect($project->fresh()->kip_external_id)->toBe('88');
});

it('creates the RK Anggota with the member id inside the proyek, not the pegawai id', function () {
    [$user, , $project, $member] = structureSetup();
    $project->update(['kip_external_id' => '88']);
    $plan = PerformancePlan::factory()->create(['project_id' => $project->id, 'team_id' => $project->team_id, 'description' => 'Entri data', 'pic_employee_id' => $member->id]);
    $writes = fakeStructureWrites(proyekExists: true);

    $this->actingAs($user)->post(route('performance-plans.push', $plan))->assertSessionHas('success');

    $post = collect($writes())->firstWhere(0, '/api/v1/rk/proyek');
    expect($post[1])->toBe(['anggotaid' => '1002', 'rencanakinerja' => 'Entri data', 'proyekid' => '88']);
    expect($plan->fresh()->kip_rkproyek_id)->toBe('555');
});

it('keeps the local project and says so when kipApp refuses', function () {
    [$user, , $project] = structureSetup();
    Http::swap(new Factory);
    Http::fake(['*' => Http::response(['status' => false, 'message' => 'Anda tidak berhak'], 200)]);

    $this->actingAs($user)->post(route('projects.push', $project), ['rk_ketua_id' => '77'])->assertSessionHas('error');
    expect($project->fresh()->kip_external_id)->toBeNull();
});

it('creates the project in kipApp right after the local one when an RK Ketua is picked', function () {
    [$user, $team, , $member] = structureSetup();
    $writes = fakeStructureWrites();

    $this->actingAs($user)->post(route('projects.store'), [
        'team_id' => $team->id, 'name' => 'Survei Contoh', 'year' => 2026, 'rk_ketua_id' => '77',
        'members' => [['employee_id' => $member->id, 'role' => 'member']],
    ])->assertSessionHas('success');

    expect(collect($writes())->firstWhere(0, '/api/v1/proyek'))->not->toBeNull();
    expect(Project::where('name', 'Survei Contoh')->orderByDesc('id')->first()->kip_external_id)->toBe('88');
});
