<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_team_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->text('uraian')->nullable();
            $table->text('obstacle')->nullable();
            $table->text('solution')->nullable();
            $table->text('follow_up_plan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_team_notes');
    }
};
