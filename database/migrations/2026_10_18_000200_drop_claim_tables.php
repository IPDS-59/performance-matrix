<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members no longer claim kegiatan; the recap reads plan_items and rk_recaps.
     * Weekly and monthly paraphrases moved to rk_recaps, so the old week and
     * month rows of the recap tables go too. Quarter rows stay (FRA).
     */
    public function up(): void
    {
        Schema::dropIfExists('weekly_recap_row_claims');
        Schema::dropIfExists('weekly_recap_rows');
        Schema::dropIfExists('weekly_team_notes');
        Schema::dropIfExists('activity_claims');

        DB::table('recap_overrides')->whereIn('period_type', ['week', 'month'])->delete();
        DB::table('recap_summaries')->where('period_type', 'month')->delete();
        DB::table('recap_locks')->whereIn('period_type', ['week', 'month'])->delete();

        Schema::table('recap_overrides', fn (Blueprint $table) => $table->dropColumn('merge_key'));
        Schema::table('kip_activities', fn (Blueprint $table) => $table->dropColumn('is_claimed'));
    }

    public function down(): void
    {
        // Claims are gone for good; there is nothing to restore.
    }
};
