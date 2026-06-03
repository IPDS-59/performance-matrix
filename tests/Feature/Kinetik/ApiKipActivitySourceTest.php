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
// Two-step flow: belumkirim → kegiatan?skpid
// ---------------------------------------------------------------------------

it('sends x-auth header on belumkirim request', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 1, 'kegiatan' => [
                ['skpid' => '1284341', 'jumlahkegiatan' => 1, 'periodeid' => 8, 'tahun' => 2026],
            ]],
        ], 200),
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
    $source->fetchUnsentActivities('340054274');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'kegiatanpegawai/belumkirim')
            && str_contains($request->url(), 'niplama=340054274')
            && $request->header('x-auth')[0] === 'Bearer test-token-abc';
    });
});

it('resolves skpids from belumkirim groups and calls kegiatan endpoint', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 2, 'kegiatan' => [
                ['skpid' => '111', 'jumlahkegiatan' => 1],
                ['skpid' => '222', 'jumlahkegiatan' => 1],
            ]],
        ], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            ['kegiatanperhariid' => '1', 'kegiatan' => 'A', 'tanggal' => '2026-06-01', 'tanggalkirim' => null],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $source->fetchUnsentActivities('340054274');

    Http::assertSentCount(3); // 1 belumkirim + 2 kegiatan calls
    Http::assertSent(fn ($r) => str_contains($r->url(), 'skpid=111'));
    Http::assertSent(fn ($r) => str_contains($r->url(), 'skpid=222'));
});

it('filters out rows where tanggalkirim is not null', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 1, 'kegiatan' => [
                ['skpid' => '500', 'jumlahkegiatan' => 1],
            ]],
        ], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response([
            ['kegiatanperhariid' => '10', 'kegiatan' => 'Unsent', 'tanggal' => '2026-06-01', 'tanggalkirim' => null],
            ['kegiatanperhariid' => '11', 'kegiatan' => 'Sent', 'tanggal' => '2026-06-02', 'tanggalkirim' => '2026-06-03'],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchUnsentActivities('340054274');

    expect($activities)->toHaveCount(1);
    expect($activities->first()->externalId)->toBe('10');
});

it('skips kegiatan entries where jumlahkegiatan is 0', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 0, 'kegiatan' => [
                ['skpid' => '999', 'jumlahkegiatan' => 0],
            ]],
        ], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchUnsentActivities('340054274');

    Http::assertSentCount(1); // only the belumkirim call
    expect($activities)->toHaveCount(0);
});

it('maps DTO fields from confirmed live response shape', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 1, 'kegiatan' => [
                ['skpid' => '1284341', 'jumlahkegiatan' => 1],
            ]],
        ], 200),
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
    $activities = $source->fetchUnsentActivities('340054274');

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

it('returns empty collection when belumkirim returns empty array', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $activities = $source->fetchUnsentActivities('340054274');

    expect($activities)->toHaveCount(0);
});

it('throws KipApiException on 500 from belumkirim', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response(
            ['message' => 'Internal Server Error'],
            500
        ),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);

    expect(fn () => $source->fetchUnsentActivities('340054274'))
        ->toThrow(KipApiException::class, 'kipApp API returned HTTP 500');
});

it('throws KipApiException on 500 from kegiatan endpoint', function () {
    Http::fake([
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([
            ['jumlahkegiatan' => 1, 'kegiatan' => [
                ['skpid' => '300', 'jumlahkegiatan' => 1],
            ]],
        ], 200),
        'kipapp.bps.go.id/api/v1/kegiatan*' => Http::response(['message' => 'error'], 500),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);

    expect(fn () => $source->fetchUnsentActivities('340054274'))
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
        'kipapp.bps.go.id/api/v1/dashboard/kegiatanpegawai/belumkirim*' => Http::response([], 200),
    ]);

    $source = new ApiKipActivitySource(new ConfigBearerAuthenticator);
    $source->fetchUnsentActivities('340054274');

    Http::assertSent(function ($request) {
        return empty($request->header('x-auth'));
    });
});
