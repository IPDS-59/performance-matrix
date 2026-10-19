<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** kipApp rkproyekid: the RK Anggota row Kinetik created in kipApp (kip_external_id is another id). */
    public function up(): void
    {
        Schema::table('performance_plans', fn (Blueprint $table) => $table->string('kip_rkproyek_id')->nullable()->after('kip_external_id'));
    }

    public function down(): void
    {
        Schema::table('performance_plans', fn (Blueprint $table) => $table->dropColumn('kip_rkproyek_id'));
    }
};
