<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each member's own RK from their yearly kipApp SKP, refreshed by the
        // activity sync. PerformancePlan keeps one row per RK text per team,
        // so it cannot say which member holds which RK.
        Schema::create('employee_rks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('kip_rk_id');
            $table->text('name');
            $table->text('leader_rk')->nullable();
            $table->string('team_kip_id')->nullable()->index();
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->unique(['employee_id', 'kip_rk_id']);
        });

        // "Fokus minggu ini": what the PJ asks a member to work on in a week.
        Schema::create('weekly_focuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'employee_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_focuses');
        Schema::dropIfExists('employee_rks');
    }
};
