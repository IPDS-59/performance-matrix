<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The head's note on a team (project_id null) or on one project of the
     * team, for one recap period. Written in Review Bersama, also after the
     * PJ locks the recap.
     */
    public function up(): void
    {
        Schema::create('leadership_notes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->cascadeOnDelete();

            // week | month | quarter
            $table->string('period_type');
            $table->integer('period_year');
            $table->date('week_start')->nullable();
            $table->unsignedSmallInteger('period_month')->nullable();
            $table->unsignedSmallInteger('period_quarter')->nullable();

            $table->text('body');

            // A user, not an employee: head accounts can exist without one.
            $table->foreignId('author_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['period_type', 'period_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leadership_notes');
    }
};
