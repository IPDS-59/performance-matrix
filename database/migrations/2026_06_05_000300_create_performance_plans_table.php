<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();
            $table->text('description');
            $table->decimal('target', 12, 2)->nullable();
            $table->string('target_unit')->nullable();
            $table->string('period_type')->default('year');
            $table->unsignedSmallInteger('period')->nullable();
            $table->foreignId('pic_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_plans');
    }
};
