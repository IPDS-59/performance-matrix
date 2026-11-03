<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // kipApp's skp/rk/copy and rk/proyek share the same ID (rkproyekid) per member, so a member's own RK has an exact projek.
        Schema::table('employee_rks', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('team_kip_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_rks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
