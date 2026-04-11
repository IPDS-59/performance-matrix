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
        Schema::create('performance_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedTinyInteger('period_month'); // 1–12
            $table->year('period_year');
            $table->decimal('achievement_percentage', 5, 2)->default(0);
            $table->text('issues')->nullable();
            $table->text('solutions')->nullable();
            $table->text('action_plan')->nullable();
            $table->timestamps();

            $table->unique(['work_item_id', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_reports');
    }
};
