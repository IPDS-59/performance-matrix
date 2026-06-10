<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * kipApp linkage for IKU (performance_indicators) and RK (performance_plans).
 *
 * kipApp RKs (rkid) are scoped to a team + employee, not a project, so
 * performance_plans gains team_id and project_id becomes nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->string('kip_external_id')->nullable()->unique()->after('id');
        });

        Schema::table('performance_plans', function (Blueprint $table) {
            $table->string('kip_external_id')->nullable()->unique()->after('id');
            $table->foreignId('team_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('performance_indicators', function (Blueprint $table) {
            $table->dropUnique(['kip_external_id']);
            $table->dropColumn('kip_external_id');
        });

        Schema::table('performance_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropUnique(['kip_external_id']);
            $table->dropColumn('kip_external_id');
        });
    }
};
