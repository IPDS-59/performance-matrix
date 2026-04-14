<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('team_annual_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->text('kpi')->nullable();           // Indikator Kinerja Utama tim
            $table->text('annual_plan')->nullable();   // Rencana Kinerja Tahunan
            $table->text('objective_1')->nullable();   // Sasaran 1
            $table->text('objective_2')->nullable();   // Sasaran 2
            $table->text('objective_3')->nullable();   // Sasaran 3
            $table->timestamps();

            $table->unique(['team_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_annual_plans');
    }
};
