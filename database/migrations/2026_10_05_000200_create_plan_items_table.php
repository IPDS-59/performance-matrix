<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What a member plans to do, before the kipApp kegiatan exists.
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performance_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->date('date_start');
            $table->date('date_end');
            $table->decimal('target', 12, 2)->nullable();
            $table->string('target_unit', 100)->nullable();
            $table->string('source', 10)->default('member'); // member | pj | rtl
            $table->unsignedBigInteger('source_ref')->nullable();
            $table->string('status', 20)->default('planned'); // planned | pushed | in_progress | done | cancelled
            $table->foreignId('kip_activity_id')->nullable()->constrained('kip_activities')->nullOnDelete();
            $table->string('kip_external_id')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->unsignedSmallInteger('push_attempts')->default(0);
            $table->text('push_error')->nullable();
            $table->string('override_status', 20)->nullable();
            $table->text('override_reason')->nullable();
            $table->foreignId('override_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamp('kip_synced_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'date_start']);
            $table->index(['employee_id', 'date_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};
