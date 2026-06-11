<?php

use App\Kinetik\Auth\ConfigBearerAuthenticator;
use App\Kinetik\Data\KipActivityData;
use App\Kinetik\Data\KipPlanData;
use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipActivitySource;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'kinetik.kip.base_url' => 'https://kipapp.bps.go.id/api',
        'kinetik.kip.token' => 'test-token-abc',
        'kinetik.kip.timeout' => 15,
    ]);
});

// ---------------------------------------------------------------------------
// fetchActivities — two-source SKP discovery
// ---------------------------------------------------------------------------

it('sends x-auth header on rkpegawai and belumkirim requests', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([
            ['jumlahrk' => 1, 'rk' => [
                ['skpid' => '1284341', 'periodeid' => 8, 'tahun' => 2026],
            ]],
        ], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            [
                'kegiatanperhariid' => '14168350',
                'rkid' => '13946149',
                'rencanakinerja' => 'Terlaksananya Kegiatan Press Release',
                'kegiatan' => 'Monitoring Penyiapan Dukungan TI',
                'tanggal' => '2026-06-02',
                'tanggalselesai' => null,
                'jammulai' => null,
                'jamselesai' => null,
                'progres' => 100,
                'capaian' => 'Press Release terlaksana',
                'datadukung' => 'https://www.youtube.com/watch?v=Cqhe-dMuoD8',
                'tanggalkirim' => null,
                'periodeid' => 8,
                'tahun' => 2026,
            ],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $source->fetchActivities('340054274');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'rkpegawai')
            && str_contains($request->url(), 'niplama=340054274')
            && $request->header('x-auth')[0] === 'Bearer test-token-abc';
    });

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'kegiatanpegawai/belumkirim')
            && str_contains($request->url(), 'niplama=340054274')
            && $request->header('x-auth')[0] === 'Bearer test-token-abc';
    });
});

it('resolves skpids from rkpegawai groups and calls kegiatan endpoint', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([
            ['jumlahrk' => 2, 'rk' => [
                ['skpid' => '111', 'periodeid' => 8],
                ['skpid' => '222', 'periodeid' => 8],
            ]],
        ], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            ['kegiatanperhariid' => '1', 'kegiatan' => 'A', 'tanggal' => '2026-06-01', 'tanggalkirim' => null],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $source->fetchActivities('340054274');

    // 1 rkpegawai + 1 belumkirim + 2 kegiatan calls
    Http::assertSentCount(4);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'skpid=111'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'skpid=222'));
});

it('also resolves skpids from belumkirim when rkpegawai yields none', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 2, 'kegiatan' => [
                ['skpid' => '333', 'jumlahkegiatan' => 1],
                ['skpid' => '444', 'jumlahkegiatan' => 1],
            ]],
        ], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            ['kegiatanperhariid' => '1', 'kegiatan' => 'A', 'tanggal' => '2026-06-01', 'tanggalkirim' => null],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $source->fetchActivities('340054274');

    Http::assertSentCount(4); // rkpegawai + belumkirim + 2 kegiatan
    Http::assertSent(fn ($r) => str_contains($r->url(), 'skpid=333'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'skpid=444'));
});

it('de-duplicates skpids appearing in both rkpegawai and belumkirim', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([
            ['rk' => [['skpid' => '500']]],
        ], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 1, 'kegiatan' => [
                ['skpid' => '500', 'jumlahkegiatan' => 1],
            ]],
        ], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            ['kegiatanperhariid' => '99', 'kegiatan' => 'X', 'tanggal' => '2026-06-01', 'tanggalkirim' => null],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $source->fetchActivities('340054274');

    // rkpegawai + belumkirim + exactly 1 kegiatan call (not 2)
    Http::assertSentCount(3);
});

it('returns submitted activities (sentAt not null) — no longer filtered out', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([
            ['rk' => [['skpid' => '500']]],
        ], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            ['kegiatanperhariid' => '10', 'kegiatan' => 'Unsent', 'tanggal' => '2026-06-01', 'tanggalkirim' => null],
            ['kegiatanperhariid' => '11', 'kegiatan' => 'Sent', 'tanggal' => '2026-06-02', 'tanggalkirim' => '2026-06-03'],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchActivities('340054274');

    // Both rows returned — submitted one is NOT dropped
    expect($activities)->toHaveCount(2);
    $ids = $activities->pluck('externalId')->all();
    expect($ids)->toContain('10');
    expect($ids)->toContain('11');
    expect($activities->firstWhere('externalId', '11')->sentAt)->toBe('2026-06-03');
});

it('returns activities when rkpegawai yields skpid but belumkirim is empty (fully-submitted employee)', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([
            ['jumlahrk' => 1, 'rk' => [
                ['skpid' => '1285321', 'periodeid' => 8, 'tahun' => 2026],
            ]],
        ], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            ['kegiatanperhariid' => '20', 'kegiatan' => 'Already sent', 'tanggal' => '2026-06-05', 'tanggalkirim' => '2026-06-06'],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchActivities('340060924');

    expect($activities)->toHaveCount(1);
    expect($activities->first()->externalId)->toBe('20');
    expect($activities->first()->sentAt)->toBe('2026-06-06');
});

it('skips belumkirim entries where jumlahkegiatan is 0', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 0, 'kegiatan' => [
                ['skpid' => '999', 'jumlahkegiatan' => 0],
            ]],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchActivities('340054274');

    // rkpegawai + belumkirim, no kegiatan call
    Http::assertSentCount(2);
    expect($activities)->toHaveCount(0);
});

it('maps DTO fields from confirmed live response shape', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([
            ['rk' => [['skpid' => '1284341']]],
        ], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            [
                'kegiatanperhariid' => '14168350',
                'rkid' => '13946149',
                'rencanakinerja' => 'Terlaksananya Kegiatan Press Release yang Baik',
                'kegiatan' => 'Monitoring Penyiapan Dukungan TI untuk Press Release Juni 2026',
                'tanggal' => '2026-06-02',
                'tanggalselesai' => null,
                'jammulai' => null,
                'jamselesai' => null,
                'progres' => 100,
                'capaian' => 'Press Release terlaksana dengan lancar',
                'datadukung' => 'https://www.youtube.com/watch?v=Cqhe-dMuoD8',
                'tanggalkirim' => null,
                'periodeid' => 8,
                'tahun' => 2026,
            ],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchActivities('340054274');

    expect($activities)->toHaveCount(1);
    $dto = $activities->first();
    expect($dto)->toBeInstanceOf(KipActivityData::class);
    expect($dto->externalId)->toBe('14168350');
    expect($dto->rkExternalId)->toBe('13946149');
    expect($dto->rkName)->toBe('Terlaksananya Kegiatan Press Release yang Baik');
    expect($dto->description)->toBe('Monitoring Penyiapan Dukungan TI untuk Press Release Juni 2026');
    expect($dto->dateStart)->toBe('2026-06-02');
    expect($dto->progress)->toBe(100);
    expect($dto->achievementNote)->toBe('Press Release terlaksana dengan lancar');
    expect($dto->evidenceUrl)->toBe('https://www.youtube.com/watch?v=Cqhe-dMuoD8');
    expect($dto->sentAt)->toBeNull();
    expect($dto->periodId)->toBe('8');
    expect($dto->sourceYear)->toBe(2026);
});

it('returns empty collection when both rkpegawai and belumkirim return empty arrays', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchActivities('340054274');

    expect($activities)->toHaveCount(0);
});

it('handles rkpegawai returning a single object {jumlahrk:0} instead of a list', function () {
    // kipApp returns an OBJECT (not a list) for employees with no RK.
    // Without normalisation, collect()->flatMap hands the closure an int -> TypeError.
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response(['jumlahrk' => 0], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchActivities('340013832');

    expect($activities)->toHaveCount(0);
    Http::assertSentCount(2); // rkpegawai + belumkirim, no kegiatan call
});

it('throws KipApiException on 500 from rkpegawai', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response(
            ['message' => 'Internal Server Error'],
            500
        ),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);

    expect(fn () => $source->fetchActivities('340054274'))
        ->toThrow(KipApiException::class, 'kipApp API returned HTTP 500');
});

it('throws KipApiException on 500 from belumkirim', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response(
            ['message' => 'Internal Server Error'],
            500
        ),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);

    expect(fn () => $source->fetchActivities('340054274'))
        ->toThrow(KipApiException::class, 'kipApp API returned HTTP 500');
});

it('throws KipApiException on 500 from kegiatan endpoint', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([
            ['rk' => [['skpid' => '300']]],
        ], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response(['message' => 'error'], 500),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);

    expect(fn () => $source->fetchActivities('340054274'))
        ->toThrow(KipApiException::class, 'kipApp API returned HTTP 500');
});

// ---------------------------------------------------------------------------
// fetchActivitiesBySkp
// ---------------------------------------------------------------------------

it('fetchActivitiesBySkp returns all rows without filtering by tanggalkirim', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            ['kegiatanperhariid' => '10', 'kegiatan' => 'Unsent', 'tanggal' => '2026-06-01', 'tanggalkirim' => null],
            ['kegiatanperhariid' => '11', 'kegiatan' => 'Sent', 'tanggal' => '2026-06-02', 'tanggalkirim' => '2026-06-03'],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchActivitiesBySkp('999');

    expect($activities)->toHaveCount(2);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'skpid=999'));
});

it('fetchActivitiesBySkp throws KipApiException on non-2xx', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([], 401),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);

    expect(fn () => $source->fetchActivitiesBySkp('999'))
        ->toThrow(KipApiException::class, 'kipApp API returned HTTP 401');
});

// ---------------------------------------------------------------------------
// Plans (fetchPlans — shape still unconfirmed)
// ---------------------------------------------------------------------------

it('sends correct URL and header when fetching plans', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([
            ['id' => '201', 'uraian' => 'Rencana Kinerja A'],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $plans = $source->fetchPlans('340054274');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'rkpegawai')
            && str_contains($request->url(), 'niplama=340054274')
            && $request->header('x-auth')[0] === 'Bearer test-token-abc';
    });

    expect($plans)->toHaveCount(1);
    expect($plans->first())->toBeInstanceOf(KipPlanData::class);
    expect($plans->first()->externalId)->toBe('201');
});

it('throws KipApiException on a 401 response for plans', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response(
            ['message' => 'Unauthorized'],
            401
        ),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);

    expect(fn () => $source->fetchPlans('340054274'))
        ->toThrow(KipApiException::class, 'kipApp API returned HTTP 401');
});

it('does not add x-auth header when token is empty', function () {
    config(['kinetik.kip.token' => null]);

    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/rkpegawai*' => Http::response([], 200),
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $source->fetchActivities('340054274');

    Http::assertSent(function ($request) {
        return empty($request->header('x-auth'));
    });
});
