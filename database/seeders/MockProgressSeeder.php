<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds mock activity_claims for June 2026 across all teams that have
 * performance_plans, so team rankings, dashboard stats, and recap pages
 * have realistic data for sanity-checking.
 *
 * Safe to re-run: clears its own rows first (by period_year=2026, period_month=6,
 * skipping the 2 real rows that exist for period_month=1).
 */
class MockProgressSeeder extends Seeder
{
    // Weeks in June 2026 (Mondays)
    private const WEEKS = [
        '2026-06-01',
        '2026-06-08',
        '2026-06-15',
    ];

    // Achievement profiles per team: [min, max] so rankings spread naturally
    private const TEAM_PROFILES = [
        1  => [70, 95],   // UMUM
        2  => [60, 85],   // HUMAS
        3  => [80, 105],  // KEUANGAN (top performer)
        4  => [55, 80],   // PERENCANAAN ANGGARAN
        7  => [65, 90],   // STATISTIK INDUSTRI
        8  => [75, 100],  // KETAHANAN SOSIAL
        9  => [50, 75],   // KETENAGAKERJAAN
        10 => [85, 110],  // STATISTIK DISTRIBUSI (best)
        11 => [60, 88],   // STATISTIK KEUANGAN
        12 => [70, 92],   // NERACA WILAYAH
        14 => [72, 96],   // MTI
        15 => [88, 108],  // DISEMINASI
        20 => [45, 70],   // MITIGASI RISIKO (lowest)
        21 => [65, 85],   // PENILAI INTERNAL
    ];

    private const OBSTACLES = [
        'Keterbatasan waktu karena tugas lain yang bersamaan.',
        'Data dari sumber eksternal terlambat masuk.',
        'Koordinasi antar seksi memerlukan waktu lebih lama dari perkiraan.',
        'Responden sulit dihubungi untuk verifikasi data.',
        'Perubahan jadwal mendadak menghambat pelaksanaan kegiatan.',
        'Keterbatasan anggaran untuk operasional lapangan.',
        'Kondisi cuaca tidak mendukung kegiatan lapangan.',
        null,
        null, // some claims have no obstacle
    ];

    private const SOLUTIONS = [
        'Melakukan pengaturan prioritas tugas dan koordinasi lintas seksi.',
        'Mengirimkan pengingat kepada responden melalui berbagai saluran komunikasi.',
        'Menyesuaikan jadwal pelaksanaan dengan kondisi yang ada.',
        'Berkoordinasi dengan pimpinan untuk penambahan sumber daya.',
        null,
        null,
    ];

    public function run(): void
    {
        // Remove previous mock runs for June 2026 (preserve Jan 2026 real data)
        DB::table('activity_claims')
            ->where('period_year', 2026)
            ->where('period_month', 6)
            ->whereNull('kip_activity_id') // only delete claims without a real kip_activity
            ->delete();

        $now = now()->toDateTimeString();
        $rows = [];

        // Load all plans grouped by team
        $plansByTeam = DB::table('performance_plans')
            ->get(['id', 'team_id'])
            ->groupBy('team_id');

        // Load employees per team from project_members (year=2026)
        $employeesByTeam = DB::table('project_members')
            ->join('projects', 'projects.id', '=', 'project_members.project_id')
            ->where('projects.year', 2026)
            ->select('project_members.employee_id', 'projects.team_id')
            ->get()
            ->groupBy('team_id')
            ->map(fn ($rows) => $rows->pluck('employee_id')->unique()->values());

        foreach ($plansByTeam as $teamId => $plans) {
            [$min, $max] = self::TEAM_PROFILES[(int) $teamId] ?? [60, 90];

            $employees = $employeesByTeam->get((string) $teamId, collect());
            if ($employees->isEmpty()) {
                continue;
            }

            foreach ($plans as $plan) {
                // Pick 1-3 employees per plan
                $assignedCount = min($employees->count(), rand(1, 3));
                $assigned = $employees->random($assignedCount);

                foreach ($assigned as $empId) {
                    // One claim per employee per plan per week (pick 1-2 weeks)
                    $weekCount = rand(1, count(self::WEEKS));
                    $weeks = array_slice(self::WEEKS, 0, $weekCount);

                    foreach ($weeks as $weekStart) {
                        $achievement = rand($min * 10, $max * 10) / 10; // one decimal
                        $target = rand(5, 20) * 5;
                        $realization = round($target * $achievement / 100, 1);
                        $obstacle = self::OBSTACLES[array_rand(self::OBSTACLES)];
                        $solution = $obstacle ? self::SOLUTIONS[array_rand(self::SOLUTIONS)] : null;

                        $actDate = date('Y-m-d', strtotime($weekStart . ' +' . rand(0, 4) . ' days'));

                        $rows[] = [
                            'kip_activity_id'    => null,
                            'employee_id'        => $empId,
                            'performance_plan_id'=> $plan->id,
                            'work_item_id'       => null,
                            'target'             => $target,
                            'realization'        => $realization,
                            'achievement'        => $achievement,
                            'target_unit'        => 'dokumen',
                            'obstacle'           => $obstacle,
                            'solution'           => $solution,
                            'follow_up_plan'     => $solution ? 'Memantau perkembangan secara berkala.' : null,
                            'activity_date_start'=> $actDate,
                            'activity_date_end'  => $actDate,
                            'status'             => 'saved',
                            'week_start'         => $weekStart,
                            'period_year'        => 2026,
                            'period_month'       => 6,
                            'period_quarter'     => 2,
                            'claimed_at'         => $now,
                            'created_at'         => $now,
                            'updated_at'         => $now,
                        ];
                    }
                }
            }
        }

        // Chunk insert to avoid hitting SQLite bind-parameter limits
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('activity_claims')->insert($chunk);
        }

        $this->command->info(sprintf('MockProgressSeeder: inserted %d activity_claims for June 2026.', count($rows)));
    }
}
