<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A member whose own sync could not run. login_required waits until the member signs in again,
        // sso_down is tried again by the scheduler. A row is removed when a sync succeeds.
        Schema::create('member_sync_waits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('reason', 20); // login_required | sso_down
            $table->string('message', 300)->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_sync_waits');
    }
};
