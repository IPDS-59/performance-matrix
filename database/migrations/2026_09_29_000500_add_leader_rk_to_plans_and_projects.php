<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * kipApp hierarchy: every proyek hangs under one RK of the team leader
     * (proyek.rencanakinerjaketua), and every member RK is cascaded from one
     * leader RK (skp/rk.rencanakinerjaatasan). The same text on both sides
     * links an RK to its proyek.
     */
    public function up(): void
    {
        Schema::table('performance_plans', function (Blueprint $table) {
            $table->text('leader_rk')->nullable()->after('description');
        });
        Schema::table('projects', function (Blueprint $table) {
            $table->text('leader_rk')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('performance_plans', fn (Blueprint $table) => $table->dropColumn('leader_rk'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('leader_rk'));
    }
};
