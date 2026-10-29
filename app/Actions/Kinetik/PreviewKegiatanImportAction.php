<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Matches the rows of a "Kegiatan Mingguan Anggota" workbook (read in the browser) to employees, projek and RK, and says
 * what is wrong with each row. Nothing is saved here; the admin reviews the result and then confirms.
 */
class PreviewKegiatanImportAction
{
    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function execute(array $rows, Carbon $weekStart): array
    {
        $monday = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $employees = Employee::where('is_active', true)->get(['id', 'name', 'display_name', 'full_name']);
        $tahun = (int) config('kinetik.kip.tahun');

        return collect($rows)->values()->map(function (array $row, int $i) use ($employees, $monday, $tahun) {
            $notes = [];
            $employee = $this->employee($employees, (string) ($row['member'] ?? ''));
            if ($employee === null) {
                $notes[] = 'Pegawai tidak ditemukan atau namanya kembar.';
            }

            $teamIds = $employee?->teams()->pluck('teams.id') ?? collect();
            $project = $employee ? $this->project($teamIds, (string) ($row['project'] ?? '')) : null;
            $options = $employee ? $this->rkOptions($employee, $teamIds, $project) : collect();
            $rk = $options->count() === 1 ? $options->first() : $options->firstWhere('own', true);
            $rk = $options->where('own', true)->count() === 1 ? $options->firstWhere('own', true) : $rk;
            if ($employee && $options->isEmpty()) {
                $notes[] = 'Tidak ada RK untuk pegawai ini.';
            }

            [$start, $end] = $this->dates($row, $monday);
            if ($start->year !== $tahun) {
                $notes[] = "Tanggal harus berada di tahun {$tahun}.";
            }
            $description = trim((string) $row['description']);
            if (mb_strlen($description) < 10) {
                $notes[] = 'Uraian minimal 10 karakter.';
            }

            $target = isset($row['target']) && $row['target'] > 0 ? (float) $row['target'] : null;
            $capaian = isset($row['realization']) ? (float) $row['realization'] : null;
            $duplicate = $employee !== null && PlanItem::where('employee_id', $employee->id)->whereDate('date_start', $start->toDateString())
                ->whereRaw('lower(description) = ?', [mb_strtolower($description)])->exists();
            if ($duplicate) {
                $notes[] = 'Sudah ada di Kinetik.';
            }

            return [
                'index' => $i,
                'sheet' => $row['sheet'] ?? null,
                'member' => $row['member'] ?? null,
                'employee_id' => $employee?->id,
                'employee_name' => $employee ? ($employee->display_name ?? $employee->name) : null,
                'project_name' => $row['project'] ?? null,
                'project_id' => $project?->id,
                'performance_plan_id' => $rk['id'] ?? null,
                'rk_options' => $options->map(fn (array $o) => ['id' => $o['id'], 'description' => $o['description']])->values()->all(),
                'date_start' => $start->toDateString(),
                'date_end' => $end->toDateString(),
                'time_start' => $row['time_start'] ?? null,
                'time_end' => $row['time_end'] ?? null,
                'description' => $description,
                'target' => $target,
                'capaian' => $capaian,
                'progress' => $this->progress($row, $target, $capaian),
                'evidence_url' => $row['link'] ?? null,
                'capaian_note' => $row['note'] ?? null,
                'notes' => $notes,
                'blocked' => $employee === null || $options->isEmpty() || $start->year !== $tahun || mb_strlen($description) < 10,
                'duplicate' => $duplicate,
            ];
        })->all();
    }

    /** "Aditya Saputra, SST, M.URP" and "Aditya Saputra" are the same person; a name shared by two people matches nobody. */
    private function employee(Collection $employees, string $name): ?Employee
    {
        $full = self::norm($name);
        $byFull = $employees->filter(fn (Employee $e) => in_array($full, [self::norm($e->name), self::norm($e->display_name), self::norm($e->full_name)], true));
        if ($byFull->count() === 1) {
            return $byFull->first();
        }

        $base = self::base($name);
        $byBase = $employees->filter(fn (Employee $e) => $base !== '' && in_array($base, [self::base($e->name), self::base($e->display_name), self::base($e->full_name)], true));

        return $byBase->count() === 1 ? $byBase->first() : null;
    }

    private function project(Collection $teamIds, string $name): ?Project
    {
        $needle = self::norm($name);
        if ($needle === '') {
            return null;
        }
        $projects = Project::whereIn('team_id', $teamIds)->where('year', (int) config('kinetik.kip.tahun'))->get(['id', 'name']);
        $exact = $projects->filter(fn (Project $p) => self::norm($p->name) === $needle);
        if ($exact->count() === 1) {
            return $exact->first();
        }
        $near = $projects->filter(fn (Project $p) => str_contains(self::norm($p->name), $needle) || str_contains($needle, self::norm($p->name)));

        return $near->count() === 1 ? $near->first() : null;
    }

    /** RK of the matched projek, else every RK of the member's teams; the member's own kipApp RK come first. @return Collection<int, array<string, mixed>> */
    private function rkOptions(Employee $employee, Collection $teamIds, ?Project $project): Collection
    {
        $plans = PerformancePlan::query()
            ->when($project !== null, fn ($q) => $q->where('project_id', $project->id))
            ->when($project === null, fn ($q) => $q->where(fn ($w) => $w->whereIn('team_id', $teamIds)->orWhereHas('project', fn ($p) => $p->whereIn('team_id', $teamIds))))
            ->orderBy('description')->get(['id', 'description']);

        $own = EmployeeRk::where('employee_id', $employee->id)->where('year', (int) config('kinetik.kip.tahun'))->pluck('name')->map(self::norm(...))->flip();

        return $plans->map(fn (PerformancePlan $p) => ['id' => $p->id, 'description' => $p->description, 'own' => $own->has(self::norm($p->description))])
            ->sortByDesc('own')->values();
    }

    /** A row with no date is a plan for the chosen week (Monday to Friday); a range never crosses into the next quarter. @return array{0: Carbon, 1: Carbon} */
    private function dates(array $row, Carbon $monday): array
    {
        $start = filled($row['date_start'] ?? null) ? Carbon::parse($row['date_start']) : $monday->copy();
        $end = filled($row['date_end'] ?? null) ? Carbon::parse($row['date_end']) : (filled($row['date_start'] ?? null) ? $start->copy() : $monday->copy()->addDays(4));
        if ($end->lt($start)) {
            $end = $start->copy();
        }
        if ($end->quarter !== $start->quarter || $end->year !== $start->year) {
            $end = $start->copy()->endOfQuarter()->startOfDay();
        }

        return [$start, $end];
    }

    /** Capaian over target when both are known, else the percent column of the sheet; always 0 to 100. */
    private function progress(array $row, ?float $target, ?float $capaian): int
    {
        $value = ($target && $capaian !== null) ? $capaian / $target * 100 : ($row['percent'] ?? 0);

        return (int) min(100, max(0, round((float) $value)));
    }

    private static function norm(?string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim((string) $text)));
    }

    /** The name before the first comma, without dots: the titles after it differ between the sheet and kipApp. */
    private static function base(?string $name): string
    {
        return self::norm(str_replace('.', '', explode(',', (string) $name)[0]));
    }
}
