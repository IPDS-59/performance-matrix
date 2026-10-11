<?php

use App\Actions\Kinetik\SyncKipCareersAction;
use App\Kinetik\Credit\CreditCalculator;
use App\Kinetik\Credit\FunctionalLevel;
use App\Models\Employee;
use App\Models\EmployeeCareer;
use App\Models\KipPerformanceRating;
use Carbon\CarbonImmutable;

function rating(Employee $e, string $id, string $start, string $end, ?string $predikat, string $status = 'Dinilai', string $jabatan = 'Pranata Komputer Ahli Pertama'): void
{
    KipPerformanceRating::create([
        'employee_id' => $e->id, 'kip_skp_id' => $id, 'period_start' => $start, 'period_end' => $end,
        'jabatan' => $jabatan, 'predikat' => $predikat, 'status' => $status,
    ]);
}

it('reads the jenjang from a kipApp jabatan and maps predikat to the Pasal 37 shares', function () {
    expect(FunctionalLevel::fromJabatan('Pranata Komputer Ahli Pertama'))->toBe(FunctionalLevel::AhliPertama)
        ->and(FunctionalLevel::fromJabatan('Statistisi Ahli Muda'))->toBe(FunctionalLevel::AhliMuda)
        ->and(FunctionalLevel::fromJabatan('Pelaksana'))->toBeNull()
        ->and(FunctionalLevel::predikatShare('Sangat Baik'))->toBe(1.5)
        ->and(FunctionalLevel::predikatShare('Baik'))->toBe(1.0)
        ->and(FunctionalLevel::predikatShare('Butuh Perbaikan'))->toBe(0.75)
        ->and(FunctionalLevel::predikatShare(null))->toBeNull();
});

it('counts AK per month across monthly and quarterly SKPs toward the next jenjang', function () {
    // Ahli Pertama from III/a in 2023, III/b since Feb 2026: next step is Ahli Muda (100 AK).
    $e = Employee::factory()->create();
    EmployeeCareer::create([
        'employee_id' => $e->id, 'jabatan' => 'Pranata Komputer Ahli Pertama', 'golongan' => 'III/b',
        'golongan_since' => '2026-02-01', 'level_since' => '2023-01-03', 'level_start_golongan' => 'III/a',
    ]);
    foreach (range(1, 12) as $m) {
        $start = sprintf('2025-%02d-01', $m);
        rating($e, "m$m", $start, CarbonImmutable::parse($start)->endOfMonth()->toDateString(), 'Baik');
    }
    rating($e, 'q1', '2026-01-01', '2026-03-31', 'Baik');
    rating($e, 'q2', '2026-04-01', '2026-06-30', 'Sangat Baik');
    rating($e, 'q3', '2026-07-01', '2026-09-30', null, 'Sedang dibuat');
    // A Pelaksana month before the JF counts nothing.
    rating($e, 'old', '2022-12-01', '2022-12-31', 'Baik', 'Dinilai', 'Pelaksana');

    $result = app(CreditCalculator::class)->forEmployee($e->fresh(), CarbonImmutable::parse('2026-09-29'));

    // 2025: 12.5; TW I: 3.125; TW II: 4.6875; TW III estimate at Baik: 3.125.
    expect($result['kind'])->toBe('jenjang')
        ->and($result['next_label'])->toBe('Ahli Muda')
        ->and($result['target'])->toEqual(100)
        ->and($result['earned'])->toEqual(23.438)
        ->and($result['estimated'])->toEqual(3.125)
        ->and($result['eligible_from'])->toBe('2028-02-01')
        ->and($result['status'])->toBe('progress')
        ->and($result['quarters'][0])->toMatchArray(['label' => 'TW III 2026', 'final' => false])
        ->and($result['quarters'][1])->toMatchArray(['label' => 'TW II 2026', 'ak' => 4.688, 'predikat' => 'Sangat Baik', 'final' => true]);
});

it('targets the next pangkat, proportional jenjang, PAK base and the 2-year rule', function () {
    $e = Employee::factory()->create();
    $career = EmployeeCareer::create([
        'employee_id' => $e->id, 'jabatan' => 'Statistisi Ahli Pertama', 'golongan' => 'III/a',
        'golongan_since' => '2023-01-01', 'level_since' => '2023-01-01', 'level_start_golongan' => 'III/a',
        'ak_base' => 48, 'ak_base_date' => '2025-12-31',
    ]);
    rating($e, 'q1', '2026-01-01', '2026-03-31', 'Baik', 'Dinilai', 'Statistisi Ahli Pertama');
    $calc = app(CreditCalculator::class);

    $pangkat = $calc->forEmployee($e->fresh(), CarbonImmutable::parse('2026-06-01'));
    expect($pangkat['kind'])->toBe('pangkat')
        ->and($pangkat['next_label'])->toBe('III/b')
        ->and($pangkat['earned'])->toEqual(51.125)
        ->and($pangkat['status'])->toBe('ready');

    // Entered Ahli Pertama at III/b: half of the 100 AK jenjang target.
    $career->update(['golongan' => 'III/b', 'golongan_since' => '2026-01-01', 'level_start_golongan' => 'III/b', 'ak_base' => null, 'ak_base_date' => null]);
    $jenjang = $calc->forEmployee($e->fresh(), CarbonImmutable::parse('2026-06-01'));
    expect($jenjang['target'])->toEqual(50)
        ->and($jenjang['status'])->toBe('progress')
        ->and($jenjang['quarters_to_go'])->toBe(15);

    $career->update(['jabatan' => 'Pelaksana']);
    expect($calc->forEmployee($e->fresh())['status'])->toBe('non_jf');
});

it('syncs golongan history and ratings from kipApp without touching the PAK value', function () {
    config(['kinetik.kip.source' => 'mock']);
    $e = Employee::factory()->create(['nip_lama' => '340000001', 'is_active' => true]);
    EmployeeCareer::create(['employee_id' => $e->id, 'ak_base' => 10, 'ak_base_date' => '2024-12-31']);

    $result = app(SyncKipCareersAction::class)->execute();

    $career = $e->career()->first();
    expect($result['employees'])->toBeGreaterThanOrEqual(1)
        ->and($career->golongan)->toBe('III/b')
        ->and($career->golongan_since->toDateString())->toBe('2025-04-01')
        ->and($career->level_since->toDateString())->toBe('2023-01-02')
        ->and($career->level_start_golongan)->toBe('III/a')
        ->and($career->ak_base)->toEqual(10)
        ->and(KipPerformanceRating::where('employee_id', $e->id)->count())->toBe(4);

    app(SyncKipCareersAction::class)->execute();
    expect(KipPerformanceRating::where('employee_id', $e->id)->count())->toBe(4);
});

it('takes the real month of old monthly SKPs that carry the whole year', function () {
    $monthly = \App\Kinetik\Data\KipRatingData::fromApiRow([
        'id' => 1, 'tahun' => 2024, 'jenisperiodepenilaian' => 1, 'namaperiodepenilaian' => 10,
        'periodeawal' => '2024-01-01', 'periodeakhir' => '2024-12-31', 'predikat' => 'Sangat Baik', 'statusskp' => 'Dinilai',
    ]);
    $quarterly = \App\Kinetik\Data\KipRatingData::fromApiRow([
        'id' => 2, 'tahun' => 2026, 'jenisperiodepenilaian' => 2, 'namaperiodepenilaian' => 2,
        'periodeawal' => '2026-04-01', 'periodeakhir' => '2026-06-30',
    ]);

    expect([$monthly->periodStart, $monthly->periodEnd])->toBe(['2024-10-01', '2024-10-31'])
        ->and([$quarterly->periodStart, $quarterly->periodEnd])->toBe(['2026-04-01', '2026-06-30'])
        ->and(\App\Kinetik\Data\KipPositionData::golongan('IV/a '))->toBe('IV/a');
});

it('reads a padded golongan and shows how each quarter was computed', function () {
    // Ahli Madya IV/a (kipApp sends "IV/a "): next step is IV/b with 150 AK, not Ahli Utama.
    $e = Employee::factory()->create();
    EmployeeCareer::create([
        'employee_id' => $e->id, 'jabatan' => 'Pranata Komputer Ahli Madya', 'golongan' => 'IV/a ',
        'golongan_since' => '2025-02-01', 'level_since' => '2024-10-01', 'level_start_golongan' => 'III/d',
    ]);
    rating($e, 'a', '2025-04-01', '2025-04-30', 'Baik', 'Dinilai', 'Pranata Komputer Ahli Madya');
    rating($e, 'b', '2025-05-01', '2025-05-31', null, 'Dinilai', 'Pranata Komputer Ahli Madya');
    rating($e, 'c', '2025-06-01', '2025-06-30', 'Sangat Baik', 'Dinilai', 'Pranata Komputer Ahli Madya');

    $result = app(CreditCalculator::class)->forEmployee($e->fresh(), CarbonImmutable::parse('2025-07-15'));

    // Apr 3,125 + May 3,125 (no predikat: estimate at Baik) + Jun 4,6875.
    expect($result['kind'])->toBe('pangkat')
        ->and($result['next_label'])->toBe('IV/b')
        ->and($result['target'])->toEqual(150)
        ->and($result['earned'])->toEqual(10.938)
        ->and($result['estimated'])->toEqual(3.125)
        ->and($result['quarters'][0]['final'])->toBeFalse()
        ->and($result['quarters'][0]['formula'])->toBe('Ahli Madya: 1 bln × 37,5 ÷ 12 × 100% + Ahli Madya: 1 bln × 37,5 ÷ 12 × 100% (estimasi) + Ahli Madya: 1 bln × 37,5 ÷ 12 × 150%');
});
