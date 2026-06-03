<?php

use App\Kinetik\Data\KipPlanData;

it('maps canonical field names correctly', function () {
    $row = ['id' => '10', 'uraian' => 'Rencana Kinerja Tahunan'];
    $dto = KipPlanData::fromApiRow($row);

    expect($dto->externalId)->toBe('10')
        ->and($dto->name)->toBe('Rencana Kinerja Tahunan')
        ->and($dto->raw)->toBe($row);
});

it('falls back to alternative id and name keys', function () {
    $dto = KipPlanData::fromApiRow([
        'rk_id' => '55',
        'nama_rencana' => 'Peningkatan kualitas data',
    ]);

    expect($dto->externalId)->toBe('55')
        ->and($dto->name)->toBe('Peningkatan kualitas data');
});

it('preserves full raw row', function () {
    $row = ['id' => '3', 'nama' => 'Plan', 'other' => 'value'];
    $dto = KipPlanData::fromApiRow($row);

    expect($dto->raw['other'])->toBe('value');
});
