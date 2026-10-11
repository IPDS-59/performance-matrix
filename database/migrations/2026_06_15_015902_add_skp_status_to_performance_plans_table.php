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
        Schema::table('performance_plans', function (Blueprint $table) {
            $table->string('skp_status')->nullable()->after('kip_external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_plans', function (Blueprint $table) {
            $table->dropColumn('skp_status');
        });
    }
};
