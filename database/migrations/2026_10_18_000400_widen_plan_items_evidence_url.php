<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** OneDrive links of kipApp bukti dukung run past 255 characters. */
    public function up(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->text('evidence_url')->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->string('evidence_url')->nullable()->change());
    }
};
