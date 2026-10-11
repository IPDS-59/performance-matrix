<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * kipApp values for these columns can exceed varchar(255) (long datadukung URLs
 * and rencanakinerja text), which overflows on PostgreSQL (prod). Widen to text.
 * SQLite stores both as TEXT already, so this is a no-op locally.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kip_activities', function (Blueprint $table) {
            $table->text('evidence_url')->nullable()->change();
            $table->text('rk_name')->nullable()->change();
        });

        // The claim copies evidence_url from the activity — widen it too.
        Schema::table('activity_claims', function (Blueprint $table) {
            $table->text('evidence_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('kip_activities', function (Blueprint $table) {
            $table->string('evidence_url')->nullable()->change();
            $table->string('rk_name')->nullable()->change();
        });

        Schema::table('activity_claims', function (Blueprint $table) {
            $table->string('evidence_url')->nullable()->change();
        });
    }
};
