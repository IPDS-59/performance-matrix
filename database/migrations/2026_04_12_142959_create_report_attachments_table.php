<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_report_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['file', 'link'])->default('link');
            $table->string('file_path')->nullable();        // storage path (private disk)
            $table->string('file_name')->nullable();        // original client filename
            $table->string('mime_type', 100)->nullable();   // e.g. image/jpeg, application/pdf
            $table->string('url', 2048)->nullable();        // for link type
            $table->string('title')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};
