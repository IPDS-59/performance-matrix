<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the member sent the kegiatan to the atasan in kipApp ("Kirim ke atasan").
        Schema::table('plan_items', fn (Blueprint $table) => $table->timestamp('kip_sent_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn('kip_sent_at'));
    }
};
