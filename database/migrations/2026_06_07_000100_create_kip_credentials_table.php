<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kip_credentials', function (Blueprint $table) {
            $table->id();

            // The kipApp x-auth Bearer token, stored encrypted via the model cast.
            $table->text('token');

            // Decoded (display-only) info about the token's owner + lifetime.
            $table->string('account_nip')->nullable();
            $table->string('account_name')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kip_credentials');
    }
};
