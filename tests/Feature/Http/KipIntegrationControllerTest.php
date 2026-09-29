<?php

use App\Kinetik\Auth\ConfigBearerAuthenticator;
use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Sources\MockKipActivitySource;
use App\Models\Employee;
use App\Models\EmployeeCareer;
use App\Models\KipActivity;
use App\Models\KipCredential;
use App\Models\KipSyncRun;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

/**
 * Build a kipApp-style JWT (header.payload.sig) with the given payload.
 * Only the payload segment matters — KipTokenInfo never verifies the signature.
 */
function fakeKipToken(array $payload): string
{
    $b64 = fn (array $a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');

    return $b64(['typ' => 'JWT', 'alg' => 'HS256']).'.'.$b64($payload).'.sig';
}

// ── Access control ────────────────────────────────────────────────────────

it('redirects guests to login', function () {
    $this->get(route('kip-integration.index'))->assertRedirect(route('login'));
});

it('forbids non-admin users', function () {
    $this->actingAs(staffUser())
        ->get(route('kip-integration.index'))
        ->assertForbidden();
});

it('renders the integration page for an admin', function () {
    $this->actingAs(adminUser())
        ->get(route('kip-integration.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Kinetik/KipIntegration')
            ->where('credential', null)
            ->has('stats')
        );
});

// ── Store token ───────────────────────────────────────────────────────────

it('stores the token and decodes its account + expiry', function () {
    $exp = now()->addDay()->timestamp;
    $token = fakeKipToken(['nip' => '340060924', 'email' => 'sukma.nirmala@bps.go.id', 'exp' => $exp]);

    $this->actingAs(adminUser())
        ->post(route('kip-integration.token'), ['token' => 'Bearer '.$token])
        ->assertRedirect()
        ->assertSessionHas('success');

    $cred = KipCredential::current();
    expect($cred)->not->toBeNull();
    expect($cred->account_nip)->toBe('340060924');
    expect($cred->account_name)->toBe('sukma.nirmala@bps.go.id');
    expect($cred->expires_at->timestamp)->toBe($exp);
    // Stored encrypted but readable via the cast; "Bearer " stripped.
    expect($cred->token)->toBe($token);
});

it('validates that a token is required', function () {
    $this->actingAs(adminUser())
        ->post(route('kip-integration.token'), [])
        ->assertSessionHasErrors(['token']);
});

// ── Authenticator resolution ──────────────────────────────────────────────

it('makes the authenticator prefer the stored credential over config', function () {
    config(['kinetik.kip.token' => 'env-token']);

    KipCredential::create(['token' => 'db-token']);

    $request = app(ConfigBearerAuthenticator::class)
        ->apply(Http::baseUrl('http://x'));

    expect($request->getOptions()['headers']['x-auth'] ?? null)->toBe('Bearer db-token');
});

// ── Centralized sync ──────────────────────────────────────────────────────

it('syncs all active employees with a nip_lama in one chunk', function () {
    $this->app->bind(KipActivitySource::class, MockKipActivitySource::class);
    KipCredential::create(['token' => 'admin-token']);

    Employee::factory()->count(2)->create([
        'is_active' => true,
        'nip_lama' => fn () => fake()->unique()->numerify('3400#####'),
    ]);
    Employee::factory()->create(['is_active' => true, 'nip_lama' => null]); // skipped

    // 2 employees <= default chunk (5) -> completes in a single step.
    $this->actingAs(adminUser())
        ->post(route('kip-integration.sync'))
        ->assertRedirect();

    $run = KipSyncRun::where('type', 'activities')->latest('id')->first();
    expect($run->status)->toBe('completed')
        ->and($run->total)->toBe(2)
        ->and($run->summary['activities'])->toBe(6);

    // MockKipActivitySource returns 3 activities per employee → 2 employees = 6
    expect(KipActivity::count())->toBe(6);
});

it('processes the activity sync in chunks across requests', function () {
    config(['kinetik.kip.activity_chunk' => 1]);
    $this->app->bind(KipActivitySource::class, MockKipActivitySource::class);
    KipCredential::create(['token' => 'admin-token']);

    Employee::factory()->count(2)->create([
        'is_active' => true,
        'nip_lama' => fn () => fake()->unique()->numerify('3400#####'),
    ]);

    $admin = adminUser();

    // Step 1: one employee processed, run still running.
    $this->actingAs($admin)->post(route('kip-integration.sync'))->assertRedirect();
    $run = KipSyncRun::where('type', 'activities')->latest('id')->first();
    expect($run->processed)->toBe(1)->and($run->status)->toBe('running');

    // Step 2: last employee, run completes.
    $this->actingAs($admin)->post(route('kip-integration.sync'))->assertRedirect();
    $run->refresh();
    expect($run->processed)->toBe(2)
        ->and($run->status)->toBe('completed')
        ->and(KipActivity::count())->toBe(6);
});

it('blocks sync when no token is configured', function () {
    config(['kinetik.kip.token' => null]);

    $this->actingAs(adminUser())
        ->post(route('kip-integration.sync'))
        ->assertRedirect()
        ->assertSessionHas('error');
});

// ── Chunked structure sync (no queue) ────────────────────────────────────

it('processes the structure sync one team per request and tracks progress', function () {
    config(['kinetik.kip.token' => 'admin-token', 'kinetik.kip.create_logins' => false]);

    Http::fake([
        'kipapp.bps.go.id/api/v1/monitoring/hirarki/daerah*' => Http::response([
            'data' => [
                ['id' => '106436', 'namaTim' => 'UMUM'],
                ['id' => '106453', 'namaTim' => 'MTI'],
            ],
        ], 200),
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response([[
            'timkerjaid' => '106436', 'namatim' => 'UMUM',
            'proyekid' => 'p1', 'namaproyek' => 'Projek A',
            'anggota' => [['anggotaid' => 'a1', 'niplama' => '340000001', 'nama' => 'Ada']],
        ]], 200),
        'kipapp.bps.go.id/api/v1/timkerja/anggota*' => Http::response([], 200),
    ]);

    $admin = adminUser();

    // Step 1: starts the run and processes the first team.
    $this->actingAs($admin)->post(route('kip-integration.sync-structure'))->assertRedirect();

    $run = KipSyncRun::where('type', 'structure')->latest('id')->first();
    expect($run)->not->toBeNull()
        ->and($run->total)->toBe(2)
        ->and($run->processed)->toBe(1)
        ->and($run->status)->toBe('running');

    // Step 2: processes the last team and completes.
    $this->actingAs($admin)->post(route('kip-integration.sync-structure'))->assertRedirect();

    $run->refresh();
    expect($run->processed)->toBe(2)
        ->and($run->status)->toBe('completed')
        ->and(Team::whereNotNull('kip_external_id')->count())->toBe(2);
});

it('blocks structure sync when no token is configured', function () {
    config(['kinetik.kip.token' => null]);

    $this->actingAs(adminUser())
        ->post(route('kip-integration.sync-structure'))
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('syncs Angka Kredit data in chunks until the run completes', function () {
    config(['kinetik.kip.source' => 'mock', 'kinetik.kip.token' => 'test', 'kinetik.kip.career_chunk' => 1]);
    Employee::factory()->count(2)->create(['is_active' => true, 'nip_lama' => fn () => (string) fake()->unique()->numerify('34#######')]);
    $admin = adminUser();

    $this->actingAs($admin)->post(route('kip-integration.sync-careers'))->assertRedirect();
    $run = KipSyncRun::where('type', 'careers')->latest('id')->first();
    expect($run->status)->toBe('running')->and($run->processed)->toBe(1);

    $this->actingAs($admin)->post(route('kip-integration.sync-careers'))->assertRedirect();
    $run->refresh();
    expect($run->status)->toBe('completed')
        ->and($run->summary['employees'])->toBe(2)
        ->and(EmployeeCareer::whereNotNull('synced_at')->count())->toBe(2);

    $this->actingAs($admin)->get(route('kip-integration.index'))
        ->assertInertia(fn ($page) => $page->where('careerRun.status', 'completed')->where('stats.careers_synced', 2));
});

it('stops the Angka Kredit sync when every employee in a chunk fails', function () {
    config(['kinetik.kip.source' => 'api', 'kinetik.kip.token' => 'expired']);
    Http::fake(['*' => Http::response(['message' => 'Unauthorized'], 401)]);
    Employee::factory()->create(['is_active' => true, 'nip_lama' => '340000009']);

    $this->actingAs(adminUser())->post(route('kip-integration.sync-careers'))->assertSessionHas('error');

    expect(KipSyncRun::where('type', 'careers')->latest('id')->first()->status)->toBe('failed');
});

it('keeps Angka Kredit sync admin-only', function () {
    $this->actingAs(staffUser())->post(route('kip-integration.sync-careers'))->assertForbidden();
});

it('stores the leader RK of each proyek and links RK under it during the structure sync', function () {
    config(['kinetik.kip.token' => 'admin-token', 'kinetik.kip.create_logins' => false]);
    Http::fake([
        'kipapp.bps.go.id/api/v1/monitoring/hirarki/daerah*' => Http::response(['data' => [['id' => '106453', 'namaTim' => 'MTI']]], 200),
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response([[
            'timkerjaid' => '106453', 'namatim' => 'MTI', 'proyekid' => 'p9', 'namaproyek' => 'Metodologi Sakernas',
            'rkketuaid' => '345759', 'rencanakinerjaketua' => 'Terlaksananya Dukungan Metodologi Kependudukan',
            'anggota' => [],
        ]], 200),
        'kipapp.bps.go.id/api/v1/*' => Http::response([], 200),
    ]);
    $team = Team::factory()->create(['kip_external_id' => '106453']);
    $plan = PerformancePlan::factory()->create(['team_id' => $team->id, 'project_id' => null, 'leader_rk' => 'Terlaksananya Dukungan Metodologi Kependudukan']);

    $this->actingAs(adminUser())->post(route('kip-integration.sync-structure'))->assertSessionHasNoErrors()->assertSessionMissing('error');

    $project = Project::where('kip_external_id', 'p9')->first();
    expect($project->leader_rk)->toBe('Terlaksananya Dukungan Metodologi Kependudukan')
        ->and($plan->fresh()->project_id)->toBe($project->id);
});
