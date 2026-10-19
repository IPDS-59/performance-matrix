<?php

use App\Actions\Kinetik\SyncSkpStatusesAction;
use App\Models\Employee;
use App\Models\MemberKipCredential;
use App\Models\SkpStatus;
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
