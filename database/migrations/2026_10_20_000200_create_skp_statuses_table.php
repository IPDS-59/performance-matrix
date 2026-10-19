<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Where each member's yearly and quarter SKP stands in kipApp, and who the atasan is. It lets an
        // atasan see what waits for them without reading every member's SKP from kipApp on each visit.
        Schema::create('skp_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('kind', 10); // yearly | quarter
            $table->unsignedTinyInteger('quarter')->default(0); // 1 to 4, 0 for yearly
            $table->string('kip_skp_id', 20);
            $table->unsignedSmallInteger('status_id')->nullable();
            $table->string('status_text', 100)->nullable();
            $table->string('atasan_pegawai_id', 20)->nullable()->index();
            $table->string('atasan_name', 150)->nullable();
            $table->unsignedSmallInteger('jml_rk')->default(0);
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'tahun', 'kind', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skp_statuses');
    }
};
