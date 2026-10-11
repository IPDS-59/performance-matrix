<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rows the PJ merged ("Gabungkan") share a merge_key: the row key
     * (plan:project) of the lead row, whose text the whole group shows.
     */
    public function up(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->string('merge_key')->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->dropColumn('merge_key');
        });
    }
};
