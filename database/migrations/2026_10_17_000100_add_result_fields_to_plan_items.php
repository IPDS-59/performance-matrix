<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            // Number achieved, against the existing numeric `target`.
            $table->decimal('capaian', 12, 2)->nullable();
            // Text sent to kipApp as `capaian` (uraian capaian).
            $table->text('capaian_note')->nullable();
            // Bukti dukung link, sent to kipApp as `datadukung`.
            $table->string('evidence_url')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn(['capaian', 'capaian_note', 'evidence_url', 'progress', 'time_start', 'time_end']));
    }
};
