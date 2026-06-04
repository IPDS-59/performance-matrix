<?php

use App\Kinetik\Data\KipActivityData;

// ---------------------------------------------------------------------------
// Confirmed primary keys (from live capture 2026-06-04)
// ---------------------------------------------------------------------------

it('maps confirmed primary field names correctly', function () {
    $row = [
        'kegiatanperhariid' => '14168350',
        'rkid' => '13946149',
        'rencanakinerja' => 'Terlaksananya Kegiatan Press Release',
        'kegiatan' => 'Monitoring Penyiapan Dukungan TI untuk Press Release',
        'tanggal' => '2026-06-02',
        'tanggalselesai' => null,
        'jammulai' => null,
        'jamselesai' => null,
        'progres' => 100,
        'capaian' => 'Press Release terlaksana dengan lancar',
        'datadukung' => 'https://www.youtube.com/watch?v=Cqhe-dMuoD8',
        'tanggalkirim' => null,
        'periodeid' => '8',
        'tahun' => 2026,
    ];

    $dto = KipActivityData::fromApiRow($row);

    expect($dto->externalId)->toBe('14168350')
        ->and($dto->rkExternalId)->toBe('13946149')
        ->and($dto->rkName)->toBe('Terlaksananya Kegiatan Press Release')
        ->and($dto->description)->toBe('Monitoring Penyiapan Dukungan TI untuk Press Release')
        ->and($dto->dateStart)->toBe('2026-06-02')
        ->and($dto->dateEnd)->toBeNull()
        ->and($dto->timeStart)->toBeNull()
        ->and($dto->timeEnd)->toBeNull()
        ->and($dto->progress)->toBe(100)
        ->and($dto->achievementNote)->toBe('Press Release terlaksana dengan lancar')
        ->and($dto->evidenceUrl)->toBe('https://www.youtube.com/watch?v=Cqhe-dMuoD8')
        ->and($dto->sentAt)->toBeNull()
        ->and($dto->periodId)->toBe('8')
        ->and($dto->sourceYear)->toBe(2026)
        ->and($dto->raw)->toBe($row);
});

it('treats tanggalkirim=null as unsent (sentAt null)', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatanperhariid' => '1',
        'kegiatan' => 'Test',
        'tanggal' => '2026-06-01',
        'tanggalkirim' => null,
    ]);

    expect($dto->sentAt)->toBeNull();
});

it('treats tanggalkirim present date string as sent', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatanperhariid' => '1',
        'kegiatan' => 'Test',
        'tanggal' => '2026-06-01',
        'tanggalkirim' => '2026-06-03',
    ]);

    expect($dto->sentAt)->toBe('2026-06-03');
});

it('treats missing tanggalkirim key as null sentAt', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatanperhariid' => '1',
        'kegiatan' => 'Test',
        'tanggal' => '2026-06-01',
    ]);

    expect($dto->sentAt)->toBeNull();
});

it('casts progress to int', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatanperhariid' => '1',
        'kegiatan' => 'Test',
        'tanggal' => '2026-06-01',
        'progres' => '75',
    ]);

    expect($dto->progress)->toBe(75);
});

it('casts sourceYear to int', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatanperhariid' => '1',
        'kegiatan' => 'Test',
        'tanggal' => '2026-06-01',
        'tahun' => '2026',
    ]);

    expect($dto->sourceYear)->toBe(2026);
});

it('falls back to kegiatan_id when kegiatanperhariid absent', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatan_id' => '99',
        'kegiatan' => 'Fallback test',
        'tanggal' => '2026-06-01',
    ]);

    expect($dto->externalId)->toBe('99');
});

it('falls back to tgl_mulai when tanggal absent', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatanperhariid' => '5',
        'kegiatan' => 'Test',
        'tgl_mulai' => '2026-06-10',
    ]);

    expect($dto->dateStart)->toBe('2026-06-10');
});

it('falls back to tgl_selesai when tanggalselesai absent', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatanperhariid' => '5',
        'kegiatan' => 'Test',
        'tanggal' => '2026-06-01',
        'tgl_selesai' => '2026-06-02',
    ]);

    expect($dto->dateEnd)->toBe('2026-06-02');
});

it('leaves nullable fields null when keys absent', function () {
    $dto = KipActivityData::fromApiRow([
        'kegiatanperhariid' => '1',
        'kegiatan' => 'Test',
        'tanggal' => '2026-06-01',
    ]);

    expect($dto->dateEnd)->toBeNull()
        ->and($dto->timeStart)->toBeNull()
        ->and($dto->timeEnd)->toBeNull()
        ->and($dto->evidenceUrl)->toBeNull()
        ->and($dto->rkExternalId)->toBeNull()
        ->and($dto->rkName)->toBeNull()
        ->and($dto->progress)->toBeNull()
        ->and($dto->achievementNote)->toBeNull()
        ->and($dto->periodId)->toBeNull()
        ->and($dto->sourceYear)->toBeNull();
});

it('preserves full raw row', function () {
    $row = [
        'kegiatanperhariid' => '5',
        'kegiatan' => 'X',
        'tanggal' => '2026-01-01',
        'extra_field' => 'keep_me',
    ];
    $dto = KipActivityData::fromApiRow($row);

    expect($dto->raw['extra_field'])->toBe('keep_me');
});
