<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progress tracking for chunked (no-queue) kipApp syncs. The browser drives the
 * sync one unit at a time; each step updates `processed` so a reload can resume.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kip_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();          // structure | activities
            $table->string('status')->default('running'); // running | completed | failed
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->json('pending')->nullable();       // remaining unit ids (team ids / niplama)
            $table->json('summary')->nullable();       // accumulated counts
            $table->string('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kip_sync_runs');
    }
};
