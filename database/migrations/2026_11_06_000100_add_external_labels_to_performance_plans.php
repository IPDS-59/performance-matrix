<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An RK of a team Kinetik does not hold (a Pusat team) names its kipApp team and projek as plain text: they are labels, not rows.
        Schema::table('performance_plans', function (Blueprint $table) {
            $table->string('external_team_name')->nullable()->after('owner_employee_id');
            $table->string('external_project_name')->nullable()->after('external_team_name');
        });
    }

    public function down(): void
    {
        Schema::table('performance_plans', fn (Blueprint $table) => $table->dropColumn(['external_team_name', 'external_project_name']));
    }
};
