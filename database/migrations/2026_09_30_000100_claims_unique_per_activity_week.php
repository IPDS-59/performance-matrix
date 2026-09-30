<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A kipApp activity can run for weeks (a monthly or quarterly task). The
     * member claims it once per week it covers, so the claim is unique per
     * activity and week instead of per activity.
     */
    public function up(): void
    {
        Schema::table('activity_claims', function (Blueprint $table) {
            $table->dropUnique(['kip_activity_id']);
            $table->unique(['kip_activity_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::table('activity_claims', function (Blueprint $table) {
            $table->dropUnique(['kip_activity_id', 'week_start']);
            $table->unique('kip_activity_id');
        });
    }
};
