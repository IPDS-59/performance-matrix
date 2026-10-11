<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A PJ locks a team's recap for one period before the meeting. While a
     * lock exists, the recap text and the member claims in that period are
     * frozen.
     */
    public function up(): void
    {
        Schema::create('recap_locks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            // week | month | quarter
            $table->string('period_type');
            $table->integer('period_year');
            $table->date('week_start')->nullable();
            $table->unsignedSmallInteger('period_month')->nullable();
            $table->unsignedSmallInteger('period_quarter')->nullable();

            $table->foreignId('locked_by')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['team_id', 'period_type', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recap_locks');
    }
};
