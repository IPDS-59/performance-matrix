<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_claims', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kip_activity_id')
                ->nullable()
                ->constrained('kip_activities')
                ->nullOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('performance_plan_id')
                ->constrained('performance_plans')
                ->cascadeOnDelete();

            $table->foreignId('work_item_id')
                ->nullable()
                ->constrained('work_items')
                ->nullOnDelete();

            $table->decimal('target', 12, 2)->nullable();
            $table->decimal('realization', 12, 2)->nullable();
            $table->decimal('achievement', 6, 2)->nullable();
            $table->string('target_unit')->nullable();

            $table->text('obstacle')->nullable();
            $table->text('solution')->nullable();
            $table->text('follow_up_plan')->nullable();

            $table->date('activity_date_start');
            $table->date('activity_date_end')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();

            $table->string('evidence_url')->nullable();
            $table->string('status')->default('draft');

            $table->date('week_start');
            $table->integer('period_year');
            $table->unsignedSmallInteger('period_quarter');
            $table->unsignedSmallInteger('period_month');

            $table->string('reserved_1')->nullable();
            $table->string('reserved_2')->nullable();
            $table->string('reserved_3')->nullable();

            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            // One claim per kip_activity (NULLs allowed on both SQLite and Postgres)
            $table->unique('kip_activity_id');

            $table->index(['employee_id', 'week_start']);
            $table->index('performance_plan_id');
            $table->index(['period_year', 'period_quarter']);
            $table->index(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_claims');
    }
};
