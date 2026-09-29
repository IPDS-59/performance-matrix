<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * kipApp's internal employee id. The monthly SKP list
     * (v1/skp?pegawaiid=) is keyed on it, not on niplama.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('kip_pegawai_id')->nullable()->after('nip_baru')->index();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['kip_pegawai_id']);
            $table->dropColumn('kip_pegawai_id');
        });
    }
};
