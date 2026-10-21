<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Which page tours a user has seen, so a tour opens by itself only once and follows the user across devices. */
    public function up(): void
    {
        Schema::create('tour_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tour_key', 100);
            $table->timestamps();
            $table->unique(['user_id', 'tour_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_completions');
    }
};
