<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            // kipApp "Masukan ke capaian SKP". On by default: every kegiatan counted so far.
            $table->boolean('is_capaian_skp')->default(true);
            // The plan item this one was copied from; its kipApp id goes out as parentid.
            $table->unsignedBigInteger('parent_id')->nullable();
        });

        // The status of a member's quarter SKP in kipApp, read whenever Kinetik loads that SKP.
        // A missing row means unknown, and unknown never locks anything.
        Schema::create('member_skp_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('quarter');
            $table->unsignedSmallInteger('status_id')->nullable();
            $table->string('status_text', 100)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'tahun', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_skp_statuses');
        Schema::table('plan_items', fn (Blueprint $table) => $table->dropColumn(['is_capaian_skp', 'parent_id']));
    }
};
