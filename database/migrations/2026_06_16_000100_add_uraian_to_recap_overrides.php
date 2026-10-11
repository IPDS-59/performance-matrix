<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->text('uraian')->nullable()->after('follow_up_plan');
        });
    }

    public function down(): void
    {
        Schema::table('recap_overrides', function (Blueprint $table) {
            $table->dropColumn('uraian');
        });
    }
};
