<?php

use App\Actions\Kinetik\SyncSkpStatusesAction;
use App\Models\Employee;
use App\Models\MemberKipCredential;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\SkpStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRolesAndPermissions();
    config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'kinetik.kip.tahun' => 2026, 'kinetik.kip.periode_id' => 8, 'kinetik.kip.source' => 'api', 'kinetik.kip.token' => 'admin-token']);
});

function atasanUser(string $pegawaiId = '555'): User
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => $pegawaiId]);
    MemberKipCredential::remember($user, 'sso-pass', 'atasan-token', now()->addHours(5));

    return $user;
}

function waitingSkp(array $attrs = []): SkpStatus
{
    $member = Employee::factory()->create(['user_id' => User::factory()->create()->id, 'kip_pegawai_id' => '777', 'is_active' => true]);

    return SkpStatus::create([
        'employee_id' => $member->id, 'tahun' => 2026, 'kind' => 'yearly', 'quarter' => 0, 'kip_skp_id' => '100',
        'status_id' => 2, 'status_text' => 'Menunggu ditetapkan', 'atasan_pegawai_id' => '555', 'atasan_name' => 'Atasan Contoh', 'jml_rk' => 6,
        'status_changed_at' => now(), ...$attrs,
    ]);
}

/** A fake kipApp whose SKP 100 is in $status. $posts collects every write. */
function fakeApproval(int $status = 2): Closure
{
    Http::swap(new Factory);
    $posts = [];
    Http::fake(function ($request) use (&$posts, $status) {
        if ($request->method() === 'POST') {
            $posts[] = [parse_url($request->url(), PHP_URL_PATH), $request->data(), $request->header('x-auth')[0] ?? null];

            return Http::response(['status' => true]);
        }
        if (str_contains($request->url(), '/v1/skp')) {
            return Http::response([['id' => '100', 'statusskpid' => $status, 'statusskp' => $status === 2 ? 'Menunggu ditetapkan' : ($status === 6 ? 'Ditetapkan' : 'Sedang dibuat'), 'atasanid' => '555', 'namaatasan' => 'Atasan Contoh', 'jmlrk' => 6]]);
        }

        return Http::response([], 404);
    });

    return function () use (&$posts) {
        return $posts;
    };
}

it('lists to an atasan only the SKP that wait for them', function () {
    $atasan = atasanUser();
    $mine = waitingSkp();
    waitingSkp(['atasan_pegawai_id' => '999']);
    waitingSkp(['status_id' => 6, 'status_text' => 'Ditetapkan']);

    $this->actingAs($atasan)->get(route('skp.approvals'))->assertInertia(fn ($page) => $page
        ->component('Skp/Approvals')
        ->has('rows', 1)
        ->where('rows.0.id', $mine->id)
        ->where('rows.0.mine', true)
        ->where('rows.0.jml_rk', 6));
});

it('is closed to someone who is nobody\'s atasan', function () {
    $user = atasanUser('123');
    waitingSkp();

    $this->actingAs($user)->get(route('skp.approvals'))->assertForbidden();
});

it('sets a yearly SKP with flag 6 and the atasan\'s own token, and tells the member', function () {
    $atasan = atasanUser();
    $skp = waitingSkp();
    $posts = fakeApproval(2);

    $this->actingAs($atasan)->post(route('skp.approve', $skp))->assertSessionHas('success');

    $post = collect($posts())->firstWhere(0, '/api/v1/skp/penetapan/kirim');
    expect($post[1])->toBe(['skpid' => '100', 'flag' => 6])->and($post[2])->toBe('Bearer atasan-token');
    expect($skp->employee->user->notifications->where('data.type', 'skp_menetapkan'))->toHaveCount(1);
});

it('does not set a SKP for someone who is not its atasan, or one that no longer waits', function () {
    $other = atasanUser('123');
    $skp = waitingSkp();
    $posts = fakeApproval(2);

    $this->actingAs($other)->post(route('skp.approve', $skp))->assertForbidden();

    $atasan = atasanUser();
    $posts = fakeApproval(6); // kipApp says it is already Ditetapkan
    $this->actingAs($atasan)->post(route('skp.approve', $skp))->assertSessionHas('error');
    expect(collect($posts())->firstWhere(0, '/api/v1/skp/penetapan/kirim'))->toBeNull();
    expect($skp->fresh()->status_id)->toBe(6);
});

it('leaves the rating of a quarter SKP to kipApp', function () {
    $atasan = atasanUser();
    $skp = waitingSkp(['kind' => 'quarter', 'quarter' => 4, 'status_id' => 5, 'status_text' => 'Sedang diperiksa']);
    $posts = fakeApproval(5);

    $this->actingAs($atasan)->post(route('skp.approve', $skp))->assertSessionHas('error');
    expect($posts())->toBe([]);
});

it('sends a waiting SKP back to the member', function () {
    $atasan = atasanUser();
    $skp = waitingSkp(['kind' => 'quarter', 'quarter' => 4, 'status_id' => 5, 'status_text' => 'Sedang diperiksa']);
    $posts = fakeApproval(5);

    $this->actingAs($atasan)->post(route('skp.sendback', $skp))->assertSessionHas('success');

    expect(collect($posts())->firstWhere(0, '/api/v1/skp/kembalikan')[1])->toBe(['id' => '100']);
});

it('stores the status of every active employee with the admin token', function () {
    $member = Employee::factory()->create(['kip_pegawai_id' => '777', 'is_active' => true]);
    Http::fake(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $q);
        if (($q['jenis'] ?? null) === '1') {
            return Http::response([['id' => '100', 'periodeid' => 8, 'statusskpid' => 2, 'statusskp' => 'Menunggu ditetapkan', 'atasanid' => '555', 'namaatasan' => 'Atasan Contoh', 'jmlrk' => 6]]);
        }

        return Http::response([['id' => '401', 'periodeid' => 8, 'periodepenilaianid' => 4, 'statusskpid' => 5, 'statusskp' => 'Sedang diperiksa', 'atasanid' => '555', 'namaatasan' => 'Atasan Contoh']]);
    });

    $result = app(SyncSkpStatusesAction::class)->execute();

    expect($result)->toBe(['employees' => 1, 'rows' => 2, 'failed' => 0]);
    expect(SkpStatus::where('employee_id', $member->id)->orderBy('kind', 'desc')->get()->map(fn ($s) => [$s->kind, $s->quarter, $s->status_id, $s->atasan_pegawai_id])->all())
        ->toBe([['yearly', 0, 2, '555'], ['quarter', 4, 5, '555']]);
});

/** A fake kipApp for rating SKP 100: the RK and behaviour rows come from $rated, writes are collected. */
function fakeRating(bool $rated): Closure
{
    Http::swap(new Factory);
    $writes = [];
    Http::fake(function ($request) use (&$writes, $rated) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if (in_array($request->method(), ['POST', 'PUT'])) {
            $writes[] = [$request->method(), $path, $request->data()];

            return Http::response(['status' => true]);
        }
        $nilai = $rated ? 90 : null;

        return match (true) {
            str_ends_with($path, '/v1/skp/penilaian/rk/periodik') => Http::response([['rkid' => 11, 'rencanakinerja' => 'Entri FRA', 'nilaiumpanbalik' => $nilai, 'levelumpanbalik' => $rated ? 2 : null]]),
            str_ends_with($path, '/v1/perilaku') => Http::response([['jenisperilakuid' => 4, 'jenisperilaku' => 'Akuntabel', 'nilaiumpanbalik' => $nilai, 'levelumpanbalik' => $rated ? 3 : null]]),
            str_ends_with($path, '/v1/skp') => Http::response([['id' => '100', 'statusskpid' => 5, 'statusskp' => 'Sedang diperiksa', 'atasanid' => '555', 'namaatasan' => 'Atasan Contoh', 'jmlrk' => 1]]),
            default => Http::response([], 404),
        };
    });

    return function () use (&$writes) {
        return $writes;
    };
}

function quarterSkp(): SkpStatus
{
    return waitingSkp(['kind' => 'quarter', 'quarter' => 4, 'status_id' => 5, 'status_text' => 'Sedang diperiksa']);
}

it('hides the Nilai SKP page while the flag is off', function () {
    config(['kinetik.kip.atasan_rating' => false]);
    $atasan = atasanUser();
    $skp = quarterSkp();

    $this->actingAs($atasan)->get(route('skp.rate', $skp))->assertNotFound();
    $this->actingAs($atasan)->put(route('skp.rate.rk', $skp), ['id' => '11', 'nilai' => 90])->assertNotFound();
});

it('saves an RK score with the level kipApp derives and the atasan\'s token', function () {
    config(['kinetik.kip.atasan_rating' => true]);
    $atasan = atasanUser();
    $skp = quarterSkp();
    $writes = fakeRating(false);

    $this->actingAs($atasan)->put(route('skp.rate.rk', $skp), ['id' => '11', 'nilai' => 99, 'umpanbalik' => 'Rapi'])->assertSessionHas('success');
    $this->actingAs($atasan)->put(route('skp.rate.perilaku', $skp), ['id' => 4, 'nilai' => 85])->assertSessionHas('success');

    expect(collect($writes())->firstWhere(1, '/api/v1/skp/rk/umpanbalik')[2])->toBe(['id' => '11', 'nilaiumpanbalik' => 99, 'levelumpanbalik' => 3, 'umpanbalik' => 'Rapi'])
        ->and(collect($writes())->firstWhere(1, '/api/v1/perilaku')[2])->toMatchArray(['skpid' => '100', 'jenisperilakuid' => 4, 'nilaiumpanbalik' => 85, 'levelumpanbalik' => 2]);
});

it('does not finish a rating while a score is missing, and finishes with flag 7 once all are in', function () {
    config(['kinetik.kip.atasan_rating' => true]);
    $atasan = atasanUser();
    $skp = quarterSkp();

    $writes = fakeRating(false);
    $this->actingAs($atasan)->post(route('skp.rate.finish', $skp))->assertSessionHas('error');
    expect(collect($writes())->firstWhere(1, '/api/v1/skp/penetapan/kirim'))->toBeNull();

    $writes = fakeRating(true);
    $this->actingAs($atasan)->post(route('skp.rate.finish', $skp))->assertRedirect(route('skp.approvals'));
    expect(collect($writes())->firstWhere(1, '/api/v1/skp/penetapan/kirim')[2])->toBe(['skpid' => '100', 'flag' => 7]);
    expect($skp->employee->user->notifications->where('data.type', 'skp_dinilai'))->toHaveCount(1);
});

it('shows the evidence of a quarter next to the SKP', function () {
    config(['kinetik.kip.atasan_rating' => true]);
    $atasan = atasanUser();
    $skp = quarterSkp();
    $team = Team::factory()->create();
    $plan = PerformancePlan::factory()->create(['project_id' => null, 'team_id' => $team->id, 'description' => 'Entri FRA']);
    PlanItem::create(['team_id' => $team->id, 'employee_id' => $skp->employee_id, 'performance_plan_id' => $plan->id, 'description' => 'Entri', 'date_start' => '2026-11-03', 'date_end' => '2026-11-03', 'target' => 10, 'target_unit' => 'dokumen', 'capaian' => 8]);
    fakeRating(false);

    $this->actingAs($atasan)->get(route('skp.approvals'))->assertInertia(fn ($page) => $page
        ->where('ratingEnabled', true)
        ->where('rows.0.evidence.0.rk', 'Entri FRA')
        ->where('rows.0.evidence.0.kegiatan', 1)
        ->where('rows.0.evidence.0.capaian', 8)
        ->where('rows.0.evidence.0.unit', 'dokumen'));
});
