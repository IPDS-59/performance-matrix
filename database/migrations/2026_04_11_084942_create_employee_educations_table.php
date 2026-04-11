<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('degree_front', 50)->nullable(); // e.g. "Dr."
            $table->string('degree_back', 100)->nullable(); // e.g. "M.Si"
            $table->string('institution')->nullable();
            $table->string('field_of_study')->nullable();
            $table->year('graduated_year')->nullable();
            $table->boolean('is_highest')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_educations');
    }
};
