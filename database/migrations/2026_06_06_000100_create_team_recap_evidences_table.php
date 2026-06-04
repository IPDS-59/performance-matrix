<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_recap_evidences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            // Optional project segment — null = team-level evidence
            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->cascadeOnDelete();

            // Recap period this evidence belongs to (week|month|quarter)
            $table->string('period_type')->default('week');
            $table->integer('period_year');
            $table->date('week_start')->nullable();
            $table->unsignedSmallInteger('period_quarter')->nullable();
            $table->unsignedSmallInteger('period_month')->nullable();

            // notula | photo | attendance (Daftar Hadir)
            $table->string('type');
            $table->string('title')->nullable();
            $table->string('url');

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['team_id', 'period_type', 'period_year']);
            $table->index(['team_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_recap_evidences');
    }
};
