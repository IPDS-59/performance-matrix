<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pairs of look-alike kegiatan an admin checked and found to be two different pieces of work. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_kegiatan_dismissals', function (Blueprint $table) {
            $table->id();
            $table->string('pair_key', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_kegiatan_dismissals');
    }
};
