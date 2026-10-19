<?php

use App\Models\Employee;
use App\Models\MemberKipCredential;
use App\Models\SkpStatus;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    seedRolesAndPermissions();
    Carbon::setTestNow('2026-10-20 08:00:00');
    config(['kinetik.kip.credential_key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'kinetik.kip.tahun' => 2026, 'kinetik.kip.periode_id' => 8, 'kinetik.kip.source' => 'api']);
});
afterEach(fn () => Carbon::setTestNow());

function skpMember(): User
{
    $user = User::factory()->create(['email' => fake()->unique()->userName().'@bps.go.id']);
    Employee::factory()->create(['user_id' => $user->id, 'kip_pegawai_id' => '777']);
    MemberKipCredential::remember($user, 'sso-pass', 'member-token', now()->addHours(5));

    return $user;
}

/**
 * A fake kipApp. $q4 is the Q4 SKP status id (null = it does not exist), $yearly the yearly status id,
 * $ikiPerRk the IKI count of the RK in a SKP. $posts collects every write.
 *
 * @return array{0: Closure}
 */
function fakeSkpApi(?int $q4 = null, int $yearly = 6, int $ikiPerRk = 1): array
{
    Http::swap(new Factory);
    $posts = [];
    $made = false;
    Http::fake(function ($request) use (&$posts, &$made, $q4, $yearly, $ikiPerRk) {
        $url = $request->url();
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $q);
        if ($request->method() === 'POST') {
            $posts[] = [parse_url($url, PHP_URL_PATH), $request->data()];
            $made = $made || str_contains($url, '/skp/bulanan');

            return Http::response(['status' => true]);
        }
        if (str_contains($url, '/v1/skp/rk/copy/bulanan')) {
            return Http::response(['rk' => [
                ['id' => '800', 'rencanakinerja' => 'RK Satu', 'flagrk' => 1, 'iscopied' => isset($q['skpid']), 'iki' => [['id' => '8001']]],
                ['id' => '900', 'rencanakinerja' => 'RK Dua', 'flagrk' => 1, 'iscopied' => false, 'iki' => [['id' => '9001']]],
            ]]);
        }
        if (str_contains($url, '/v1/skp/rk')) {
            return Http::response([['rkid' => '1', 'rencanakinerja' => 'RK Satu', 'jmliki' => $ikiPerRk, 'jmlkegiatan' => 3]]);
        }
        if (str_contains($url, '/v1/skp')) {
            if (($q['jenis'] ?? null) === '1') {
                return Http::response([['id' => '100', 'periodeid' => 8, 'statusskpid' => $yearly, 'statusskp' => $yearly === 6 ? 'Ditetapkan' : 'Sedang dibuat', 'jmlrk' => 2, 'namaatasan' => 'Atasan', 'atasanid' => '555', 'unitkerjaid' => '100', 'wilayahid' => '7200_11']]);
            }
            $rows = [['id' => '301', 'periodeid' => 8, 'periodepenilaianid' => 3, 'statusskpid' => 7, 'statusskp' => 'Dinilai']];
            if ($q4 !== null || $made) {
                $q4 ??= 1;
                $rows[] = ['id' => '401', 'periodeid' => 8, 'periodepenilaianid' => 4, 'statusskpid' => $q4, 'statusskp' => $q4 === 1 ? 'Sedang dibuat' : 'Sedang diperiksa'];
            }

            return Http::response($rows);
        }

        return Http::response([], 404);
    });

    return [function () use (&$posts) {
        return $posts;
    }];
}

it('shows the yearly SKP and each quarter, and offers to create the quarter that has no SKP', function () {
    $user = skpMember();
    fakeSkpApi();

    $this->actingAs($user)->get(route('skp.index'))->assertInertia(fn ($page) => $page
        ->component('Skp/Index')
        ->where('overview.yearly.status', 'Ditetapkan')
        ->where('overview.quarters.2.status', 'Dinilai')
        ->where('overview.quarters.2.can_cancel', false)
        ->where('overview.quarters.3.exists', false)
        ->where('overview.quarters.3.can_create', true)
        ->where('overview.quarters.3.is_current', true)
        ->where('overview.quarters.3.candidates.0.id', '800'));
});

it('does not offer a quarter SKP while the yearly SKP is not Ditetapkan', function () {
    $user = skpMember();
    fakeSkpApi(yearly: 1);

    $this->actingAs($user)->get(route('skp.index'))->assertInertia(fn ($page) => $page
        ->where('overview.quarters.3.can_create', false)
        ->where('overview.quarters.3.create_blocker', 'SKP Tahunan harus Ditetapkan atasan dulu.')
        ->where('overview.yearly.can_submit', true));
});

it('creates a quarter SKP with only the chosen RK', function () {
    $user = skpMember();
    [$posts] = fakeSkpApi();

    $this->actingAs($user)->post(route('skp.quarter.create', 4), ['rk_ids' => ['900']])->assertSessionHas('success');

    $post = collect($posts())->firstWhere(0, '/api/v1/skp/bulanan');
    expect($post[1])->toMatchArray(['id' => '100', 'periodepenilaianid' => 4, 'flagmethod' => 1])
        ->and($post[1]['data'])->toBe([['rk' => '900', 'iki' => ['9001'], 'flagrk' => 1]]);
});

it('asks for at least one RK', function () {
    $user = skpMember();
    fakeSkpApi();

    $this->actingAs($user)->post(route('skp.quarter.create', 4), ['rk_ids' => []])->assertSessionHasErrors('rk_ids');
});

it('adds RK to a quarter SKP that is still Sedang dibuat, and refuses once it was sent', function () {
    $user = skpMember();
    [$posts] = fakeSkpApi(q4: 1);

    $this->actingAs($user)->put(route('skp.quarter.rks', 4), ['rk_ids' => ['900']])->assertSessionHas('success');
    $post = collect($posts())->firstWhere(0, '/api/v1/skp/bulanan');
    expect($post[1]['flagmethod'])->toBe(2)->and(collect($post[1]['data'])->pluck('rk')->all())->toBe(['800', '900']);

    fakeSkpApi(q4: 5);
    $this->actingAs($user)->put(route('skp.quarter.rks', 4), ['rk_ids' => ['900']])->assertSessionHas('error');
});

it('sends a quarter SKP to be rated with flag 5 and takes it back', function () {
    $user = skpMember();
    [$posts] = fakeSkpApi(q4: 1);

    $this->actingAs($user)->post(route('skp.submit'), ['kind' => 'quarter', 'quarter' => 4])->assertSessionHas('success');
    expect(collect($posts())->firstWhere(0, '/api/v1/skp/penetapan/kirim')[1])->toBe(['skpid' => '401', 'flag' => 5]);

    [$posts] = fakeSkpApi(q4: 5);
    $this->actingAs($user)->post(route('skp.cancel'), ['kind' => 'quarter', 'quarter' => 4])->assertSessionHas('success');
    expect(collect($posts())->firstWhere(0, '/api/v1/skp/kembalikan')[1])->toBe(['id' => '401']);
});

it('sends a yearly SKP to be set with flag 2', function () {
    $user = skpMember();
    [$posts] = fakeSkpApi(yearly: 1);

    $this->actingAs($user)->post(route('skp.submit'), ['kind' => 'yearly'])->assertSessionHas('success');
    expect(collect($posts())->firstWhere(0, '/api/v1/skp/penetapan/kirim')[1])->toBe(['skpid' => '100', 'flag' => 2]);
});

it('does not send a SKP that has an RK without IKI, like kipApp, and does not send twice', function () {
    $user = skpMember();
    [$posts] = fakeSkpApi(q4: 1, ikiPerRk: 0);

    $this->actingAs($user)->post(route('skp.submit'), ['kind' => 'quarter', 'quarter' => 4])->assertSessionHas('error');
    expect(collect($posts())->firstWhere(0, '/api/v1/skp/penetapan/kirim'))->toBeNull();

    fakeSkpApi(q4: 5);
    $this->actingAs($user)->post(route('skp.submit'), ['kind' => 'quarter', 'quarter' => 4])->assertSessionHas('error');
});

it('blocks the changes while an admin only views as the member', function () {
    $member = skpMember();
    $admin = adminUser();
    fakeSkpApi();
    $this->actingAs($admin)->post(route('admin.impersonate.start', $member));

    $this->post(route('skp.quarter.create', 4), ['rk_ids' => ['900']])->assertSessionHas('error');
    Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/skp/bulanan'));
});

it('stores the sent SKP for the atasan and tells them', function () {
    $user = skpMember();
    $atasan = User::factory()->create();
    Employee::factory()->create(['user_id' => $atasan->id, 'kip_pegawai_id' => '555']);
    fakeSkpApi(yearly: 1);

    $this->actingAs($user)->post(route('skp.submit'), ['kind' => 'yearly'])->assertSessionHas('success');

    expect(SkpStatus::where('kind', 'yearly')->first())->atasan_pegawai_id->toBe('555')->kip_skp_id->toBe('100');
    expect($atasan->notifications->where('data.type', 'skp_sent'))->toHaveCount(1);
});

/** A fake kipApp whose Q4 SKP 401 is in $q4 with one RK; $copied makes the one IKI come from the atasan. $writes collects POST and DELETE. */
function fakeIkiApi(int $q4 = 1, bool $copied = false): Closure
{
    Http::swap(new Factory);
    $writes = [];
    $extra = false;
    Http::fake(function ($request) use (&$writes, &$extra, $q4, $copied) {
        $path = parse_url($request->url(), PHP_URL_PATH);
        if (in_array($request->method(), ['POST', 'DELETE'])) {
            $writes[] = [$request->method(), $path, $request->data(), $request->header('x-auth')[0] ?? null];
            $extra = $extra || $request->method() === 'POST';

            return Http::response(['status' => true]);
        }

        return match (true) {
            str_ends_with($path, '/v1/skp/iki') => Http::response(array_values(array_filter([
                ['ikiid' => 'i1', 'rkid' => '1', 'iki' => 'Persentase entri tepat waktu 90 persen', 'iscopied' => $copied, 'isatasan' => false],
                $extra ? ['ikiid' => 'i2', 'rkid' => '1', 'iki' => 'IKI baru dari anggota', 'iscopied' => false, 'isatasan' => false] : null,
            ]))),
            str_ends_with($path, '/v1/skp/rk/copy/bulanan') => Http::response(['rk' => []]),
            str_ends_with($path, '/v1/skp/rk') => Http::response([['id' => '1', 'rencanakinerja' => 'RK Satu', 'jmliki' => 1]]),
            str_ends_with($path, '/v1/skp') => Http::response(str_contains($request->url(), 'jenis=1')
                ? [['id' => '100', 'periodeid' => 8, 'statusskpid' => 6, 'statusskp' => 'Ditetapkan', 'atasanid' => '555']]
                : [['id' => '401', 'periodeid' => 8, 'periodepenilaianid' => 4, 'statusskpid' => $q4, 'statusskp' => $q4 === 1 ? 'Sedang dibuat' : 'Sedang diperiksa']]),
            default => Http::response([], 404),
        };
    });

    return function () use (&$writes) {
        return $writes;
    };
}

it('hides the IKI form while the flag is off', function () {
    config(['kinetik.kip.iki_write' => false]);
    $user = skpMember();
    fakeIkiApi();

    $this->actingAs($user)->post(route('skp.iki.store'), ['kind' => 'quarter', 'quarter' => 4, 'rk_id' => '1', 'text' => 'IKI yang cukup panjang'])->assertNotFound();
    $this->actingAs($user)->get(route('skp.index'))->assertInertia(fn ($p) => $p->where('overview.quarters.3.can_edit_iki', false));
});

it('lists the RK of a draft SKP with their IKI', function () {
    config(['kinetik.kip.iki_write' => true]);
    $user = skpMember();
    fakeIkiApi();

    $this->actingAs($user)->get(route('skp.index'))->assertInertia(fn ($p) => $p
        ->where('overview.quarters.3.can_edit_iki', true)
        ->where('overview.quarters.3.rk_list.0.name', 'RK Satu')
        ->where('overview.quarters.3.rk_list.0.iki.0.own', true));
});

it('adds an IKI to an RK with the keys kipApp uses and the member\'s own token', function () {
    config(['kinetik.kip.iki_write' => true]);
    $user = skpMember();
    $writes = fakeIkiApi();

    $this->actingAs($user)->post(route('skp.iki.store'), ['kind' => 'quarter', 'quarter' => 4, 'rk_id' => '1', 'text' => '  IKI baru dari anggota  '])->assertSessionHas('success');

    $post = collect($writes())->firstWhere(1, '/api/v1/skp/iki');
    expect($post[2])->toBe(['rkid' => '1', 'iki' => 'IKI baru dari anggota'])->and($post[3])->toBe('Bearer member-token');
});

it('refuses a short IKI, an RK that is not in the SKP, and a SKP that is no longer Sedang dibuat', function () {
    config(['kinetik.kip.iki_write' => true]);
    $user = skpMember();
    $writes = fakeIkiApi();

    $this->actingAs($user)->post(route('skp.iki.store'), ['kind' => 'quarter', 'quarter' => 4, 'rk_id' => '1', 'text' => 'pendek'])->assertSessionHasErrors('text');
    $this->actingAs($user)->post(route('skp.iki.store'), ['kind' => 'quarter', 'quarter' => 4, 'rk_id' => '999', 'text' => 'IKI yang cukup panjang'])->assertSessionHas('error');

    $writes = fakeIkiApi(q4: 5);
    $this->actingAs($user)->post(route('skp.iki.store'), ['kind' => 'quarter', 'quarter' => 4, 'rk_id' => '1', 'text' => 'IKI yang cukup panjang'])->assertSessionHas('error');
    expect($writes())->toBeEmpty();
});

it('deletes an IKI the member added but not one copied from the atasan', function () {
    config(['kinetik.kip.iki_write' => true]);
    $user = skpMember();

    $writes = fakeIkiApi();
    $this->actingAs($user)->delete(route('skp.iki.destroy'), ['kind' => 'quarter', 'quarter' => 4, 'iki_id' => 'i1'])->assertSessionHas('success');
    expect(collect($writes())->firstWhere(0, 'DELETE')[2])->toBe(['id' => 'i1']);

    $writes = fakeIkiApi(copied: true);
    $this->actingAs($user)->delete(route('skp.iki.destroy'), ['kind' => 'quarter', 'quarter' => 4, 'iki_id' => 'i1'])->assertSessionHas('error');
    expect($writes())->toBeEmpty();
});
