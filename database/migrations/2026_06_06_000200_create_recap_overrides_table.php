<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recap_overrides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            // The RK (Rencana Kinerja) row in the recap this override paraphrases
            $table->foreignId('performance_plan_id')
                ->constrained('performance_plans')
                ->cascadeOnDelete();

            // month | quarter
            $table->string('period_type');
            $table->integer('period_year');
            $table->unsignedSmallInteger('period_quarter')->nullable();
            $table->unsignedSmallInteger('period_month')->nullable();

            // Paraphrased aggregate text (overrides the concatenated claim text)
            $table->text('obstacle')->nullable();
            $table->text('solution')->nullable();
            $table->text('follow_up_plan')->nullable();

            // FRA follow-up (quarterly recap)
            $table->string('follow_up_evidence_url')->nullable();
            $table->foreignId('follow_up_pic_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
            $table->date('follow_up_deadline')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['performance_plan_id', 'period_type', 'period_year']);
            $table->index(['team_id', 'period_type', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recap_overrides');
    }
};
