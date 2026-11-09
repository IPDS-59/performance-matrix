<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** "Permasalahan": what got in the way of a kegiatan, written by the member or the PJ in the weekly meeting (the old Excel had the column). */
    public function up(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->text('kendala')->nullable()->after('capaian_note'));
    }

    public function down(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn('kendala'));
    }
};
