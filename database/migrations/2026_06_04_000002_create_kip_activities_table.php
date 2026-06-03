<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kip_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->string('external_id')->unique();
            $table->string('nip_lama')->index();

            $table->text('description')->nullable();
            $table->date('activity_date_start');
            $table->date('activity_date_end')->nullable();
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->string('evidence_url')->nullable();

            // RK (rencana kinerja) linkage
            $table->string('rk_external_id')->nullable()->index();
            $table->string('rk_name')->nullable();

            // Progress and achievement
            $table->unsignedSmallInteger('progress')->nullable();
            $table->text('achievement_note')->nullable();

            // Period metadata from kipApp
            $table->string('period_id')->nullable();
            $table->integer('source_year')->nullable();

            // Tracks when the activity was officially submitted in kipApp (null = unsent)
            $table->date('sent_at')->nullable();

            $table->json('raw_payload')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->boolean('is_claimed')->default(false);

            // Reserved for future fields without requiring a new migration
            $table->string('reserved_1')->nullable();
            $table->string('reserved_2')->nullable();
            $table->string('reserved_3')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'activity_date_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kip_activities');
    }
};
