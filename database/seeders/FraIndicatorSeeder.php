<?php

namespace Database\Seeders;

use App\Models\FraIndicator;
use App\Models\FraQuarterValue;
use Illuminate\Database\Seeder;

/**
 * Loads the LK_Prov rows and the values already in the 2026 Kertas Kerja.
 * Safe to run again: indicators are updated, but quarter values that exist
 * are left alone, so figures entered in Kinetik are never overwritten.
 */
class FraIndicatorSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode((string) file_get_contents(database_path('data/fra-indicators-2026.json')), true);

        foreach ($data['indicators'] as $row) {
            $indicator = FraIndicator::updateOrCreate(
                ['year' => $data['year'], 'code' => $row['code']],
                collect($row)->only(['sort_order', 'tujuan', 'sasaran_code', 'sasaran_name', 'name', 'kind', 'period_type', 'unit_type', 'percent_label', 'unit', 'x_label', 'y_label', 'target_x', 'target_y'])->all(),
            );

            foreach ($row['quarters'] as $quarter => $values) {
                FraQuarterValue::firstOrCreate(['fra_indicator_id' => $indicator->id, 'quarter' => (int) $quarter], $values);
            }
        }
    }
}
