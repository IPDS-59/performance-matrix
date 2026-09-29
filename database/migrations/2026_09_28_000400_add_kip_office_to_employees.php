<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where kipApp says the employee works now (v1/pegawai/lokasi). Kinetik
     * only keeps staff of BPS Provinsi Sulawesi Tengah; the check is cached.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('kip_office')->nullable()->after('kip_pegawai_id');
            $table->boolean('kip_in_office')->nullable()->after('kip_office');
            $table->timestamp('kip_office_checked_at')->nullable()->after('kip_in_office');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['kip_office', 'kip_in_office', 'kip_office_checked_at']);
        });
    }
};
