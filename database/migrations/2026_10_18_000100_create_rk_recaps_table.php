<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What the PJ wrote for one RK of a team in one week or month: a paraphrase of
        // the members' entries and one target, satuan and capaian for the whole RK.
        Schema::create('rk_recaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_plan_id')->constrained()->cascadeOnDelete();
            $table->string('period_type', 5); // week | month
            $table->date('period_start'); // the Monday of the week, or the 1st of the month
            $table->text('paraphrase')->nullable();
            $table->decimal('target', 12, 2)->nullable();
            $table->string('target_unit', 100)->nullable();
            $table->decimal('capaian', 12, 2)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'performance_plan_id', 'period_type', 'period_start'], 'rk_recaps_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rk_recaps');
    }
};
