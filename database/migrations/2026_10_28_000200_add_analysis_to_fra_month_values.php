<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The analysis columns of the LK_Prov sheet, written per month. The quarterly export takes the quarter's last month.
        Schema::table('fra_month_values', function (Blueprint $table) {
            $table->text('obstacle')->nullable();
            $table->text('solution')->nullable();
            $table->text('follow_up')->nullable();
            $table->string('pic')->nullable();
            $table->string('deadline')->nullable();
            $table->text('evidence_url')->nullable();
            $table->text('previous_follow_up_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('fra_month_values', function (Blueprint $table) {
            $table->dropColumn(['obstacle', 'solution', 'follow_up', 'pic', 'deadline', 'evidence_url', 'previous_follow_up_url']);
        });
    }
};
