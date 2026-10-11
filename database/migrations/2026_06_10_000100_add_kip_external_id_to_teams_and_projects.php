<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stable kipApp identifiers so the structure sync can upsert idempotently.
 * `teams.kip_external_id`  = kipApp timkerjaid.
 * `projects.kip_external_id` = kipApp proyekid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('kip_external_id')->nullable()->unique()->after('code');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('kip_external_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['kip_external_id']);
            $table->dropColumn('kip_external_id');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['kip_external_id']);
            $table->dropColumn('kip_external_id');
        });
    }
};
