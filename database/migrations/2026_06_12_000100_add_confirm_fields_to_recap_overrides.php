<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->date('week_start')->nullable()->after('period_month');
            $table->timestamp('confirmed_at')->nullable()->after('created_by');
            $table->foreignId('confirmed_by')
                ->nullable()
                ->after('confirmed_at')
                ->constrained('employees')
                ->nullOnDelete();

            $table->index(['team_id', 'period_type', 'period_year', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropIndex(['team_id', 'period_type', 'period_year', 'week_start']);
            $table->dropColumn(['week_start', 'confirmed_at', 'confirmed_by']);
        });
    }
};
