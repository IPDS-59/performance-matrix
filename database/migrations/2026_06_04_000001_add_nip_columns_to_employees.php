<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nip_lama', 20)->nullable()->after('employee_number');
            $table->string('nip_baru', 30)->nullable()->after('nip_lama');

            $table->index('nip_lama');
            $table->index('nip_baru');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['nip_lama']);
            $table->dropIndex(['nip_baru']);
            $table->dropColumn(['nip_lama', 'nip_baru']);
        });
    }
};
