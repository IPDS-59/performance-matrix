<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Services\Kinetik\KegiatanDuplicateFinder;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Matches the rows of a "Kegiatan Mingguan Anggota" workbook (read in the browser) to employees, projek and RK, and says
 * what is wrong with each row. Nothing is saved here; the admin reviews the result and then confirms.
 */
class PreviewKegiatanImportAction
{
    public function __construct(private readonly KegiatanDuplicateFinder $finder = new KegiatanDuplicateFinder) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function execute(array $rows, Carbon $weekStart): array
    {
        $monday = $weekStart->copy()->startOfWeek(Carbon::MONDAY);
        $employees = Employee::where('is_active', true)->get(['id', 'name', 'display_name', 'full_name', 'nip_baru', 'nip_lama']);
        $tahun = (int) config('kinetik.kip.tahun');

        return collect($rows)->values()->flatMap(function (array $row) use ($employees, $monday, $tahun) {
            $notes = [];
            $nip = trim((string) ($row['nip'] ?? ''));
            $employee = $nip !== '' ? $this->employeeByNip($employees, $nip) : $this->employee($employees, (string) ($row['member'] ?? ''));
            if ($employee === null) {
                $notes[] = $nip !== '' ? "NIP {$nip} tidak ada di Kinetik." : 'Pegawai tidak ditemukan atau namanya kembar. Unggah Daftar Pegawai agar dicocokkan lewat NIP.';
            }

            $teamIds = $employee?->teams()->pluck('teams.id') ?? collect();
            $project = $employee ? $this->project($teamIds, (string) ($row['project'] ?? '')) : null;
            $options = $employee ? $this->rkOptions($employee, $teamIds, $project) : collect();
            $rk = $options->count() === 1 ? $options->first() : $options->firstWhere('own', true);
            $rk = $options->where('own', true)->count() === 1 ? $options->firstWhere('own', true) : $rk;
            if ($employee && $project === null && filled($row['project'] ?? null)) {
                $notes[] = 'Projek "'.$row['project'].'" tidak ditemukan di tim pegawai ini. Pilih RK yang sesuai secara manual.';
            }
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

            $segments = $this->segments($start, $end);
            $base = [
                'sheet' => $row['sheet'] ?? null,
                'member' => $row['member'] ?? null,
                'employee_id' => $employee?->id,
                'employee_name' => $employee ? ($employee->display_name ?? $employee->name) : null,
                'project_name' => $row['project'] ?? null,
                'project_id' => $project?->id,
                'performance_plan_id' => $rk['id'] ?? null,
                'rk_options' => $options->map(fn (array $o) => ['id' => $o['id'], 'description' => $o['description']])->values()->all(),
                'time_start' => $row['time_start'] ?? null,
                'time_end' => $row['time_end'] ?? null,
                'description' => $description,
                'progress' => $this->progress($row, $target, $capaian),
                'evidence_url' => $row['link'] ?? null,
                'capaian_note' => $row['note'] ?? null,
            ];

            // An SKP is one quarter, so a kegiatan that crosses a quarter becomes one kegiatan per quarter. Each shows in every week it spans.
            return collect($segments)->map(function (array $segment, int $k) use ($base, $notes, $employee, $description, $options, $tahun, $target, $capaian, $segments) {
                $duplicate = $employee !== null && PlanItem::where('employee_id', $employee->id)->whereDate('date_start', $segment[0]->toDateString())
                    ->whereRaw('lower(description) = ?', [mb_strtolower($description)])->exists();
                $own = $notes;
                if (count($segments) > 1) {
                    $own[] = 'Dipecah per triwulan ('.($k + 1).'/'.count($segments).'); target dan capaian ada di bagian pertama.';
                }
                if ($duplicate) {
                    $own[] = 'Sudah ada di Kinetik.';
                }
                // The same work worded differently, or on the neighbouring day: often a kegiatan the member already filed in kipApp.
                $similar = $employee !== null && ! $duplicate ? $this->finder->find($employee->id, $description, $segment[0]->toDateString(), $segment[1]->toDateString()) : null;
                if ($similar !== null) {
                    $own[] = 'Mirip dengan kegiatan '.($similar['in_kipapp'] ? 'di kipApp' : 'di Kinetik').' ('.$similar['date'].'): "'.mb_strimwidth($similar['description'], 0, 80, '…').'". Tidak dicentang; centang bila ini kegiatan yang berbeda.';
                }

                return [
                    ...$base,
                    'date_start' => $segment[0]->toDateString(),
                    'date_end' => $segment[1]->toDateString(),
                    'target' => $k === 0 ? $target : null,
                    'capaian' => $k === 0 ? $capaian : null,
                    'notes' => $own,
                    'blocked' => $employee === null || $options->isEmpty() || $segment[0]->year !== $tahun || mb_strlen($description) < 10,
                    'duplicate' => $duplicate,
                    'similar' => $similar === null ? null : ['description' => $similar['description'], 'date' => $similar['date'], 'in_kipapp' => $similar['in_kipapp'], 'score' => $similar['score']],
                ];
            });
        })->values()->map(fn (array $r, int $i) => ['index' => $i, ...$r])->all();
    }

    /** @return list<array{0: Carbon, 1: Carbon}> the range cut at quarter ends */
    private function segments(Carbon $start, Carbon $end): array
    {
        $out = [];
        for ($from = $start->copy(); $from->lte($end); $from = $to->copy()->addDay()) {
            $to = $from->copy()->endOfQuarter()->startOfDay();
            $to = $to->gt($end) ? $end->copy() : $to;
            $out[] = [$from->copy(), $to->copy()];
        }

        return $out;
    }

    /** The NIP is the identity: a name can be spelled many ways, a NIP cannot. */
    private function employeeByNip(Collection $employees, string $nip): ?Employee
    {
        $found = $employees->filter(fn (Employee $e) => $e->nip_baru === $nip || $e->nip_lama === $nip);

        return $found->count() === 1 ? $found->first() : null;
    }

    /** "Aditya Saputra, SST, M.URP", "Aditya Saputra" and "Aditya Saputra SST" are one person; a name shared by two people matches nobody. */
    private function employee(Collection $employees, string $name): ?Employee
    {
        $key = self::key($name);
        if ($key === '') {
            return null;
        }
        $found = $employees->filter(fn (Employee $e) => in_array($key, array_map(self::key(...), array_filter([$e->name, $e->display_name, $e->full_name])), true));

        return $found->count() === 1 ? $found->first() : null;
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

        if ($near->count() === 1) {
            return $near->first();
        }

        // Sheets often write the projek a little differently ("Manajemen Mitra" for "Manajemen Mitra Statistik"):
        // take the one projek that shares most of the words, if it clearly beats the others.
        $words = fn (string $t) => collect(preg_split('/[^a-z0-9]+/', self::norm($t), -1, PREG_SPLIT_NO_EMPTY))->reject(fn ($w) => mb_strlen($w) < 3 || in_array($w, ['dan', 'yang', 'dari', 'pada', 'dalam', 'untuk', 'data'], true))->unique();
        $mine = $words($name);
        $scored = $projects->map(fn (Project $p) => ['p' => $p, 'score' => $mine->isEmpty() ? 0 : $mine->intersect($words($p->name))->count() / max($mine->count(), $words($p->name)->count(), 1)])
            ->sortByDesc('score')->values();

        return ($scored->first()['score'] ?? 0) >= 0.6 && (($scored[1]['score'] ?? 0) <= $scored->first()['score'] - 0.2) ? $scored->first()['p'] : null;
    }

    /**
     * RK of the matched projek; when that projek has none in Kinetik (its RK may hang on another projek, or not be synced),
     * every RK of the member's teams. The member's own kipApp RK come first. @return Collection<int, array<string, mixed>>
     */
    private function rkOptions(Employee $employee, Collection $teamIds, ?Project $project): Collection
    {
        $inTeams = fn ($q) => $q->where(fn ($w) => $w->whereIn('team_id', $teamIds)->orWhereHas('project', fn ($p) => $p->whereIn('team_id', $teamIds)));
        $plans = $project !== null ? PerformancePlan::where('project_id', $project->id)->orderBy('description')->get(['id', 'description']) : collect();
        if ($plans->isEmpty()) {
            $plans = PerformancePlan::query()->tap($inTeams)->orderBy('description')->get(['id', 'description']);
        }

        $own = EmployeeRk::where('employee_id', $employee->id)->where('year', (int) config('kinetik.kip.tahun'))->pluck('name')->map(self::norm(...))->flip();

        // The same RK text exists once per projek and per member in kipApp: show it once.
        return $plans->map(fn (PerformancePlan $p) => ['id' => $p->id, 'description' => $p->description, 'own' => $own->has(self::norm($p->description))])
            ->sortByDesc('own')->unique(fn (array $o) => self::norm($o['description']))->values();
    }

    /** A row with no date is a plan for the chosen week (Monday to Friday); a range keeps its real end. @return array{0: Carbon, 1: Carbon} */
    private function dates(array $row, Carbon $monday): array
    {
        $start = filled($row['date_start'] ?? null) ? Carbon::parse($row['date_start']) : $monday->copy();
        $end = filled($row['date_end'] ?? null) ? Carbon::parse($row['date_end']) : (filled($row['date_start'] ?? null) ? $start->copy() : $monday->copy()->addDays(4));
        if ($end->lt($start)) {
            $end = $start->copy();
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

    /**
     * The person's name without academic titles. Kinetik stores names like "Yudha Subakti S.Si." while the sheets write
     * "Yudha Subakti, S.Si": text after a comma goes, and so does any word with a dot or a short all-capital word (SST, SE, MM).
     */
    private static function key(?string $name): string
    {
        $words = preg_split('/\s+/', trim(explode(',', (string) $name)[0]), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $kept = [];
        foreach ($words as $i => $word) {
            $isTitle = $i > 0 && (str_contains($word, '.') || (mb_strtoupper($word) === $word && mb_strlen($word) <= 5 && ctype_alpha($word)));
            if ($i === 0 && preg_match('/^(dr|drs|ir|prof|h|hj)\.?$/i', $word)) {
                continue;
            }
            if (! $isTitle) {
                $kept[] = mb_strtolower($word);
            }
        }

        return implode(' ', $kept);
    }
}
