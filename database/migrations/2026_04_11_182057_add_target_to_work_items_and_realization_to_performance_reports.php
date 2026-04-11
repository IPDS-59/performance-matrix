<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_items', function (Blueprint $table) {
            // Annual target value (e.g. 100 for "100 documents")
            $table->decimal('target', 10, 2)->default(1)->after('description');
            // Unit label for display (e.g. "Dokumen", "Kegiatan", "Laporan")
            $table->string('target_unit', 50)->default('Kegiatan')->after('target');
        });

        Schema::table('performance_reports', function (Blueprint $table) {
            // Cumulative realization value for this period
            $table->decimal('realization', 10, 2)->default(0)->after('achievement_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reports', function (Blueprint $table) {
            $table->dropColumn('realization');
        });

        Schema::table('work_items', function (Blueprint $table) {
            $table->dropColumn(['target', 'target_unit']);
        });
    }
};
