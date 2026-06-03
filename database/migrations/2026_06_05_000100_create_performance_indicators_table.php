<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->integer('year');
            $table->string('code')->nullable();
            $table->string('name');
            $table->decimal('target', 12, 2)->nullable();
            $table->string('target_unit')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_indicators');
    }
};
