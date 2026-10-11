<?php

use App\Kinetik\Auth\ConfigBearerAuthenticator;
use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipStructureSource;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'kinetik.kip.base_url' => 'https://kipapp.bps.go.id/api',
        'kinetik.kip.token' => 'test-token-abc',
        'kinetik.kip.timeout' => 15,
        'kinetik.kip.periode_id' => 8,
        'kinetik.kip.unitkerja_id' => '100',
        'kinetik.kip.wilayah_id' => '7200_11',
    ]);
});

it('enumerates all unit teams from the monitoring/hirarki/daerah directory', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/monitoring/hirarki/daerah*' => Http::response([
            'totalTim' => 2,
            'namaKepala' => 'Dr. Daryanto, M.M',
            'niplamaKepala' => '340013341',
            'data' => [
                ['id' => '106436', 'namaTim' => 'UMUM', 'niplamaKetuaTim' => '340013832'],
                ['id' => '106453', 'namaTim' => 'MTI', 'niplamaKetuaTim' => '340054274'],
            ],
        ], 200),
    ]);

    $teams = (new ApiKipStructureSource(new ConfigBearerAuthenticator))->fetchTeams();

    expect($teams)->toHaveCount(2)
        ->and($teams->pluck('externalId')->all())->toBe(['106436', '106453'])
        ->and($teams->first()->name)->toBe('UMUM');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'monitoring/hirarki/daerah')
        && str_contains($request->url(), 'unitkerjaid=100')
        && str_contains($request->url(), 'wilayahid=7200_11')
        && $request->header('x-auth')[0] === 'Bearer test-token-abc');
});

it('fetches team projects with inline members', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response([
            [
                'timkerjaid' => '106436',
                'namatim' => 'UMUM',
                'niplamaketua' => '340013832',
                'namaketua' => 'Imron',
                'proyekid' => '427670',
                'namaproyek' => 'Pengembangan SDM',
                'anggota' => [
                    ['anggotaid' => 'a1', 'niplama' => '340053881', 'nama' => 'Asmawati'],
                ],
            ],
        ], 200),
    ]);

    $projects = (new ApiKipStructureSource(new ConfigBearerAuthenticator))->fetchTeamProjects('106436');

    expect($projects)->toHaveCount(1)
        ->and($projects->first()->externalId)->toBe('427670')
        ->and($projects->first()->leaderNipLama)->toBe('340013832')
        ->and($projects->first()->members)->toHaveCount(1);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'v1/proyek')
        && str_contains($request->url(), 'timkerjaid=106436'));
});

it('fetches team members and drops rows without niplama', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/timkerja/anggota*' => Http::response([
            ['anggotaid' => 'm1', 'niplama' => '340017503', 'nama' => 'Verawati'],
            ['anggotaid' => 'm2', 'niplama' => '', 'nama' => 'No NIP'],
        ], 200),
    ]);

    $members = (new ApiKipStructureSource(new ConfigBearerAuthenticator))->fetchTeamMembers('106436');

    expect($members)->toHaveCount(1)
        ->and($members->first()->nipLama)->toBe('340017503');
});

it('throws on a non-successful response', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/proyek*' => Http::response('nope', 500),
    ]);

    (new ApiKipStructureSource(new ConfigBearerAuthenticator))->fetchTeamProjects('106436');
})->throws(KipApiException::class);
