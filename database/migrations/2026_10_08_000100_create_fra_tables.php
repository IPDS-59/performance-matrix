<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The organisation-level rows of the Kertas Kerja (sheet LK_Prov): IKU and proksi.
        // These are not the teams' own kipApp indicators in performance_indicators.
        Schema::create('fra_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('sort_order');
            $table->string('tujuan')->nullable();
            $table->string('sasaran_code', 20);
            $table->text('sasaran_name')->nullable();
            $table->string('code', 20);
            $table->text('name');
            $table->string('kind', 10); // IKU | Proksi
            $table->string('period_type', 12); // Triwulanan | Tahunan
            $table->string('unit_type', 10); // percent (X over Y rows) | value (typed on the row)
            $table->boolean('percent_label')->default(false); // a value row the sheet marks "%"
            $table->string('unit', 50)->nullable();
            $table->text('x_label')->nullable();
            $table->text('y_label')->nullable();
            $table->decimal('target_x', 14, 4)->nullable();
            $table->decimal('target_y', 14, 4)->nullable();
            $table->foreignId('owner_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->timestamps();

            $table->unique(['year', 'code']);
        });

        // Cumulative allocation and realisation per quarter, plus the analysis columns.
        Schema::create('fra_quarter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fra_indicator_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('quarter');
            $table->decimal('allocation_x', 14, 4)->nullable();
            $table->decimal('allocation_y', 14, 4)->nullable();
            $table->decimal('realization_x', 14, 4)->nullable();
            $table->decimal('realization_y', 14, 4)->nullable();
            $table->text('obstacle')->nullable();
            $table->text('solution')->nullable();
            $table->text('follow_up')->nullable();
            $table->string('pic')->nullable();
            $table->string('deadline')->nullable();
            $table->text('evidence_url')->nullable();
            $table->text('previous_follow_up_url')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fra_indicator_id', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fra_quarter_values');
        Schema::dropIfExists('fra_indicators');
    }
};
