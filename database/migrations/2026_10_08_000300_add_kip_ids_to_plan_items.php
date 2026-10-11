<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Needed to update the kegiatan later: kipApp wants the whole row again.
        Schema::table('plan_items', function (Blueprint $table) {
            $table->string('kip_skp_id', 30)->nullable();
            $table->string('kip_rk_id', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn(['kip_skp_id', 'kip_rk_id']));
    }
};
