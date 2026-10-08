<?php

use App\Models\FraIndicator;
use App\Models\FraQuarterValue;
use App\Services\Kinetik\FraCalculator;
use App\Services\Kinetik\FraReport;
use Database\Seeders\FraIndicatorSeeder;

// Expected numbers are the cached values of "[ISI] KERTAS KERJA TW 2 2026.xlsx", sheet LK_Prov.

it('loads the 20 LK_Prov rows of 2026 and keeps entered figures on a second run', function () {
    $this->seed(FraIndicatorSeeder::class);
    expect(FraIndicator::count())->toBe(20)->and(FraQuarterValue::count())->toBe(80);

    $value = FraQuarterValue::where('quarter', 2)->whereHas('indicator', fn ($q) => $q->where('code', '2.5.1.1'))->first();
    $value->update(['realization_x' => 111]);

    $this->seed(FraIndicatorSeeder::class);
    expect(FraIndicator::count())->toBe(20)->and($value->fresh()->realization_x)->toBe(111.0);
});

it('computes capaian like the sheet', function () {
    $calc = new FraCalculator;

    expect($calc->capaian(60, 94))->toBe(120.0)                     // row 79, TW2 vs quarter, capped
        ->and(round($calc->capaian(120, 94), 4))->toBe(78.3333)    // row 79, TW2 vs year
        ->and(round($calc->capaian(4.5200, 4.6269), 4))->toBe(102.3650) // row 87, TW2
        ->and($calc->capaian(0, 150))->toBe(120.0)                  // no target but a result
        ->and($calc->capaian(0, 0))->toBeNull()
        ->and($calc->capaian(50, 0))->toBeNull();
});

it('turns a SAKIP score into predikat and correction', function () {
    $calc = new FraCalculator;

    expect($calc->sakipPredicate(null))->toBe('D/Sangat Kurang')
        ->and($calc->sakipPredicate(78.1))->toBe('BB/Sangat Baik')
        ->and($calc->sakipCorrection('BB/Sangat Baik'))->toBe(0.10)
        ->and($calc->sakipCorrection('A/Memuaskan'))->toBe(0.0)
        ->and($calc->pkoPredicate(85))->toBe('BAIK');
});

it('builds the report for TW2 from the loaded 2026 data', function () {
    $this->seed(FraIndicatorSeeder::class);
    $report = (new FraReport(new FraCalculator))->build(FraIndicator::with('quarterValues')->where('year', 2026)->get(), 2, 78.1);
    $row = fn (string $code) => collect($report['indicators'])->firstWhere('code', $code);

    expect($row('2.5.1.1')['quarters'][2])->capaian_quarter->toBe(120.0)
        ->and(round($row('2.5.1.1')['quarters'][2]['capaian_year'], 4))->toBe(78.3333)
        ->and(round($row('2.6.1.1')['quarters'][2]['capaian_quarter'], 2))->toBe(118.75)  // 19/40 over 40% allocation
        ->and(round($row('2.6.1.1')['quarters'][2]['capaian_year'], 3))->toBe(59.375)
        ->and($row('1.1.1.1')['quarters'][2]['capaian_year'])->toBeNull()                 // realisation 0 -> "-"
        ->and($row('2.5.1.1')['final'])->toBe(78.33333333333333 * 0.9)                   // BB: 10% correction
        ->and($report['summary']['sakip_predicate'])->toBe('BB/Sangat Baik');
});
