<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A repeated kegiatan folded into another one when it was sent to kipApp (the folded one is cancelled and points to the kept one). */
    public function up(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->unsignedBigInteger('merged_into_id')->nullable()->index()->after('parent_id'));
    }

    public function down(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn('merged_into_id'));
    }
};
