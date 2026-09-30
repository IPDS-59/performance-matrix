<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PJ who last corrected a member's target or realisasi on the team
     * recap. Null while the numbers are the member's own.
     */
    public function up(): void
    {
        Schema::table('activity_claims', function (Blueprint $table) {
            $table->foreignId('adjusted_by')->nullable()->after('achievement')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('adjusted_by');
        });
    }
};
