<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RK tambahan (kipApp jeniskinerja 2): an RK a member adds to their own SKP outside the team's RK. It belongs to one person.
        Schema::table('performance_plans', function (Blueprint $table) {
            $table->boolean('is_additional')->default(false)->after('pic_employee_id');
            $table->foreignId('owner_employee_id')->nullable()->after('is_additional')->constrained('employees')->nullOnDelete();
        });
        Schema::table('employee_rks', function (Blueprint $table) {
            $table->boolean('is_additional')->default(false)->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('performance_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_employee_id');
            $table->dropColumn('is_additional');
        });
        Schema::table('employee_rks', fn (Blueprint $table) => $table->dropColumn('is_additional'));
    }
};
