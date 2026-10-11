<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IKU come from the Kepala's Perjanjian Kinerja: the Sasaran is the PK RK,
     * the IKU is an IKI of that RK. Each team keeps a row for every IKU its
     * RK Ketua support.
     */
    public function up(): void
    {
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->text('sasaran')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('performance_indicators', fn (Blueprint $table) => $table->dropColumn('sasaran'));
    }
};
