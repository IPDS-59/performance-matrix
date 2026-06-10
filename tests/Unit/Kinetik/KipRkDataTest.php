<?php

use App\Kinetik\Data\KipRkData;

it('parses a percentage target from IKI text', function () {
    expect(KipRkData::parseTarget('Persentase Supervisi: 100%'))->toBe([100.0, '%']);
});

it('parses a "Sebanyak N satuan" target', function () {
    expect(KipRkData::parseTarget('Jumlah Laporan Sebanyak 4 dokumen'))->toBe([4.0, 'dokumen']);
});

it('parses a "sebesar N persen" target', function () {
    expect(KipRkData::parseTarget('Capaian sebesar 100 persen'))->toBe([100.0, 'persen']);
});

it('returns nulls when there is no number', function () {
    expect(KipRkData::parseTarget('Terlaksananya kegiatan dengan baik'))->toBe([null, null]);
    expect(KipRkData::parseTarget(''))->toBe([null, null]);
    expect(KipRkData::parseTarget(null))->toBe([null, null]);
});

it('maps an skp/rk row with parsed target', function () {
    $rk = KipRkData::fromApiRow(
        ['rkid' => '13962139', 'rencanakinerja' => 'Terlaksananya Dukungan Metodologi', 'timkerjaid' => '106453'],
        'Persentase Dukungan: 100%',
    );

    expect($rk->externalId)->toBe('13962139')
        ->and($rk->name)->toBe('Terlaksananya Dukungan Metodologi')
        ->and($rk->teamExternalId)->toBe('106453')
        ->and($rk->target)->toBe(100.0)
        ->and($rk->targetUnit)->toBe('%');
});
