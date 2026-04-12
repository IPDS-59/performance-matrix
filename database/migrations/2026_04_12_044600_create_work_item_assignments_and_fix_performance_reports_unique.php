<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Explicit constraint names stay ≤63 chars (PostgreSQL identifier limit).
    private const OLD_UNIQUE = 'pr_work_item_period_unique';

    private const NEW_UNIQUE = 'pr_work_item_reporter_period_unique';

    public function up(): void
    {
        Schema::create('work_item_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('target', 10, 2)->default(1);
            $table->string('target_unit', 50)->default('Kegiatan');
            $table->timestamps();

            $table->unique(['work_item_id', 'employee_id'], 'wia_work_item_employee_unique');
        });

        // Widen the unique key to include the reporter so each employee can
        // submit their own report for the same work item in the same period.
        Schema::table('performance_reports', function (Blueprint $table) {
            $table->dropUnique(self::OLD_UNIQUE);
            $table->unique(['work_item_id', 'reported_by', 'period_year', 'period_month'], self::NEW_UNIQUE);
        });
    }

    public function down(): void
    {
        Schema::table('performance_reports', function (Blueprint $table) {
            $table->dropUnique(self::NEW_UNIQUE);
            $table->unique(['work_item_id', 'period_year', 'period_month'], self::OLD_UNIQUE);
        });

        Schema::dropIfExists('work_item_assignments');
    }
};
