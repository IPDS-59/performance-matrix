<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A kegiatan that runs for several weeks is one kegiatan, but the weekly meeting needs to see its progress of each week
     * ("gak ketahuan progres minggu ininya"). One row per kegiatan and week holds that week's cumulative capaian, progres
     * and permasalahan. The kegiatan itself keeps the latest values, which are what goes to kipApp.
     */
    public function up(): void
    {
        Schema::create('plan_item_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_item_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->decimal('capaian', 14, 2)->nullable();
            $table->unsignedSmallInteger('progress')->nullable();
            $table->text('kendala')->nullable();
            $table->timestamps();
            $table->unique(['plan_item_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_item_weeks');
    }
};
