<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The PJ's output row of a week: one kegiatan, or several merged ones.
        // A kegiatan has no row until the PJ saves or merges it.
        Schema::create('weekly_recap_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('week_start');
            $table->text('pj_uraian')->nullable();
            $table->text('obstacle')->nullable();
            $table->text('solution')->nullable();
            $table->text('follow_up_plan')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('employees')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'week_start']);
        });

        Schema::create('weekly_recap_row_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_recap_row_id')->constrained()->cascadeOnDelete();
            // A claim sits in exactly one row.
            $table->foreignId('activity_claim_id')->unique()->constrained()->cascadeOnDelete();
        });

        // Monthly summary per Projek, like the weekly rows: uraian plus Permasalahan, Solusi and RTL.
        Schema::table('recap_summaries', function (Blueprint $table) {
            $table->text('obstacle')->nullable()->after('body');
            $table->text('solution')->nullable()->after('obstacle');
            $table->text('follow_up_plan')->nullable()->after('solution');
        });
    }

    public function down(): void
    {
        Schema::table('recap_summaries', fn (Blueprint $table) => $table->dropColumn(['obstacle', 'solution', 'follow_up_plan']));
        Schema::dropIfExists('weekly_recap_row_claims');
        Schema::dropIfExists('weekly_recap_rows');
    }
};
