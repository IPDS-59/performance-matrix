<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PJ's monthly or quarterly narrative for one Projek of the team
     * ("Ringkasan Bulanan/Triwulanan"). project_id null = RK without a Projek.
     */
    public function up(): void
    {
        Schema::create('recap_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            // month | quarter
            $table->string('period_type');
            $table->integer('period_year');
            $table->unsignedSmallInteger('period_month')->nullable();
            $table->unsignedSmallInteger('period_quarter')->nullable();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'period_type', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recap_summaries');
    }
};
