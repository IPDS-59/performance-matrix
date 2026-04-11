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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leader_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('objective')->nullable();
            $table->text('kpi')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->index(['team_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
