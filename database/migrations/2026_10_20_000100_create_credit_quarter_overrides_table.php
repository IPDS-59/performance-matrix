<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A manual AK for one quarter, replacing the calculated one. The calculated value is kept
        // next to it so a reader can always see what the estimate said.
        Schema::create('credit_quarter_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('quarter', 7); // "2026-3" = TW III 2026
            $table->decimal('ak', 8, 3);
            $table->decimal('computed_ak', 8, 3);
            $table->string('reason', 500);
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('edited_by_role', 10); // pegawai | admin
            $table->timestamps();
            $table->unique(['employee_id', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_quarter_overrides');
    }
};
