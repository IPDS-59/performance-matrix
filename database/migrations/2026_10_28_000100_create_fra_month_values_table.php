<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Monthly X and Y (or one value) per indicator, like the "Data Monitoring" sheet. The quarter realisation is built from these.
        Schema::create('fra_month_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fra_indicator_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('month');
            $table->decimal('x', 14, 4)->nullable();
            $table->decimal('y', 14, 4)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fra_indicator_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fra_month_values');
    }
};
