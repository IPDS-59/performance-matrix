<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Career data for Angka Kredit: position and golongan from kipApp
     * v1/pegawai, plus an optional starting AK from the last PAK.
     */
    public function up(): void
    {
        Schema::create('employee_careers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();

            $table->string('jabatan')->nullable();
            $table->string('golongan', 8)->nullable();
            $table->string('pangkat')->nullable();
            // First kipApp record with the current golongan / jenjang.
            $table->date('golongan_since')->nullable();
            $table->date('level_since')->nullable();
            // Golongan when the current jenjang started (proportional target).
            $table->string('level_start_golongan', 8)->nullable();

            // Official AK from the last PAK; replaces the estimate up to ak_base_date.
            $table->decimal('ak_base', 8, 3)->nullable();
            $table->date('ak_base_date')->nullable();

            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kip_performance_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('kip_skp_id')->unique();

            $table->date('period_start');
            $table->date('period_end');
            $table->string('jabatan')->nullable();
            $table->string('predikat')->nullable();
            $table->decimal('nilai_prestasi', 6, 2)->nullable();
            $table->string('status')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kip_performance_ratings');
        Schema::dropIfExists('employee_careers');
    }
};
