<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Team;
use App\Services\Kinetik\KegiatanDuplicateFinder;
use Illuminate\Support\Facades\DB;

/** Saves the rows an admin confirmed in the import preview as planned kegiatan. */
class ImportKegiatanAction
{
    public function __construct(private readonly KegiatanDuplicateFinder $finder = new KegiatanDuplicateFinder) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, skipped: list<array{description: string, reason: string}>}
     */
    public function execute(array $rows, ?Employee $admin): array
    {
        $created = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, $admin, &$created, &$skipped) {
            foreach ($rows as $row) {
                $plan = PerformancePlan::with('project')->find($row['performance_plan_id']);
                $teamId = $plan?->project?->team_id ?? $plan?->team_id;
                // An RK owned by this person alone (a Pusat team's, or an RK tambahan) is theirs whatever their teams hold; it files under their main team.
                $owned = $plan !== null && (int) $plan->owner_employee_id === (int) $row['employee_id'];
                if ($owned && $teamId === null) {
                    $teamId = Employee::find($row['employee_id'])?->teams()->orderByDesc('employee_team.is_primary')->value('teams.id');
                }
                $team = $teamId ? Team::find($teamId) : null;

                if ($team === null || (! $owned && ! $team->members()->where('employees.id', $row['employee_id'])->exists())) {
                    $skipped[] = ['description' => $row['description'], 'reason' => 'RK ini bukan milik tim pegawai.'];

                    continue;
                }
                $duplicate = PlanItem::where('employee_id', $row['employee_id'])->whereDate('date_start', $row['date_start'])
                    ->whereRaw('lower(description) = ?', [mb_strtolower(trim($row['description']))])->exists();
                if ($duplicate) {
                    $skipped[] = ['description' => $row['description'], 'reason' => 'Sudah ada di Kinetik.'];

                    continue;
                }

                // Checked again at save: the admin may have ticked a row marked "mirip" (ignore_similar), nothing else gets past it.
                $similar = ($row['ignore_similar'] ?? false) ? null : $this->finder->find((int) $row['employee_id'], $row['description'], $row['date_start'], $row['date_end'] ?? null);
                if ($similar !== null) {
                    $skipped[] = ['description' => $row['description'], 'reason' => 'Mirip dengan kegiatan '.($similar['in_kipapp'] ? 'di kipApp' : 'di Kinetik').' ('.$similar['date'].').'];

                    continue;
                }

                PlanItem::create([
                    'team_id' => $team->id,
                    'employee_id' => $row['employee_id'],
                    'performance_plan_id' => $plan->id,
                    'project_id' => $plan->project_id ?? ($row['project_id'] ?? null),
                    'description' => trim($row['description']),
                    'date_start' => $row['date_start'],
                    'date_end' => $row['date_end'],
                    'time_start' => $row['time_start'] ?? null,
                    'time_end' => $row['time_end'] ?? null,
                    'target' => $row['target'] ?? null,
                    'target_unit' => $plan->target_unit,
                    'capaian' => $row['capaian'] ?? null,
                    'progress' => $row['progress'] ?? 0,
                    'evidence_url' => $row['evidence_url'] ?? null,
                    'capaian_note' => $row['capaian_note'] ?? null,
                    'is_capaian_skp' => true,
                    'status' => 'planned',
                    'source' => 'import',
                    'created_by' => $admin?->id,
                ]);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }
}
