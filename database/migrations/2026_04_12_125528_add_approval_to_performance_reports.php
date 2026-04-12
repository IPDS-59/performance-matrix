<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reports', function (Blueprint $table) {
            $table->string('approval_status')->default('pending')->after('action_plan'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_note')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('performance_reports', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['approval_status', 'reviewed_by', 'reviewed_at', 'review_note']);
        });
    }
};
