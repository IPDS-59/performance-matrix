<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // T4: after one rejected login Kinetik stops using the stored password
        // until the member enters it again.
        Schema::table('member_kip_credentials', function (Blueprint $table) {
            $table->timestamp('login_failed_at')->nullable();
            $table->string('last_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('member_kip_credentials', fn (Blueprint $table) => $table->dropColumn(['login_failed_at', 'last_error']));
    }
};
