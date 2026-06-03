<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Replace the plain indexes added in add_nip_columns with unique ones.
            // NULLs stay non-conflicting on both SQLite and Postgres.
            $table->dropIndex(['nip_lama']);
            $table->dropIndex(['nip_baru']);

            $table->unique('nip_lama');
            $table->unique('nip_baru');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['nip_lama']);
            $table->dropUnique(['nip_baru']);

            $table->index('nip_lama');
            $table->index('nip_baru');
        });
    }
};
