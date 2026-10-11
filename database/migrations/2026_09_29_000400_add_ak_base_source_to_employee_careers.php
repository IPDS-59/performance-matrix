<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who entered the PAK value: "pegawai" (the employee, not checked yet)
     * or "admin" (checked against the PAK document).
     */
    public function up(): void
    {
        Schema::table('employee_careers', function (Blueprint $table) {
            $table->string('ak_base_source', 16)->nullable()->after('ak_base_date');
        });
    }

    public function down(): void
    {
        Schema::table('employee_careers', function (Blueprint $table) {
            $table->dropColumn('ak_base_source');
        });
    }
};
