<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_report_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', ['submitted', 'resubmitted', 'approved', 'rejected']);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('performance_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_report_reviews');
    }
};
