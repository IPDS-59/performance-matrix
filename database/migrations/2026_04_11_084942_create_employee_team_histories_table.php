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
        Schema::create('employee_team_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('started_at');
            $table->date('ended_at')->nullable(); // null = current assignment
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('employee_id');
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_team_histories');
    }
};
