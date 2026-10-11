<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * kipApp RKs are team-scoped, so the Projek a member worked on is chosen at
     * claim time (Probis item 6) and drives the recap segmentation (item 9).
     * Overrides carry it too so a PJ paraphrases per Projek + RK.
     */
    public function up(): void
    {
        Schema::table('activity_claims', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('performance_plan_id')
                ->constrained('projects')
                ->nullOnDelete();
        });

        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('performance_plan_id')
                ->constrained('projects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('activity_claims', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
