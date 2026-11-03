<?php

namespace App\Actions\Kinetik;

use App\Models\Employee;
use App\Models\PerformancePlan;
use App\Models\PlanItem;
use App\Models\Project;
use App\Models\Team;

/**
 * Data that is missing a relationship Kinetik (and kipApp) rely on. Each check says why it matters, how many rows break
 * it and shows a sample, so an admin can fix the source instead of finding out when a push fails.
 */
class BuildDataIntegrityAction
{
    private const SAMPLE = 25;

    public function __construct(private readonly LinkPlansToProjectsAction $links) {}

    /** @return list<array{key: string, title: string, why: string, fix: string, severity: string, count: int, rows: list<array<string, mixed>>}> */
    public function execute(): array
    {
        $rkNoProject = PerformancePlan::with('team:id,name')->whereNull('project_id')->get()
            ->filter(fn (PerformancePlan $p) => ! $this->links->projectOptional($p));

        $itemsNoProject = PlanItem::with(['employee:id,name', 'performancePlan:id,description,project_id,team_id'])
            ->whereNull('project_id')->whereNull('kip_external_id')->where('status', '!=', 'cancelled')
            ->whereHas('performancePlan', fn ($q) => $q->whereNull('project_id'))->get()
            ->filter(fn (PlanItem $i) => ! $this->links->projectOptionalFor($i->performancePlan, $i->employee));

        return [
            $this->check('rk_no_project', 'RK tanpa projek', 'Kegiatan pada RK ini tidak bisa dikirim ke kipApp sebelum projeknya jelas, dan rekap per projek tidak lengkap.', 'Jalankan Sinkronisasi Struktur (membaca RK tiap projek dari kipApp), atau pilih projek pada kegiatannya.', 'high',
                $rkNoProject->count(), $rkNoProject->take(self::SAMPLE)->map(fn (PerformancePlan $p) => ['RK' => $p->description, 'Tim' => $p->team?->name])->values()->all()),
            $this->check('items_no_project', 'Kegiatan belum terkirim dan tanpa projek', 'Pengiriman ke kipApp ditahan untuk kegiatan ini sampai projeknya dipilih.', 'Buka kegiatannya dan pilih projek, atau sinkronkan struktur supaya RK-nya terhubung.', 'high',
                $itemsNoProject->count(), $itemsNoProject->take(self::SAMPLE)->map(fn (PlanItem $i) => ['Kegiatan' => $i->description, 'Pegawai' => $i->employee?->name, 'RK' => $i->performancePlan?->description])->values()->all()),
            $this->check('items_no_rk', 'Kegiatan tanpa RK', 'Kegiatan tanpa RK tidak punya tempat di SKP, jadi tidak bisa dikirim.', 'Buka kegiatannya dan pilih RK.', 'high',
                PlanItem::whereNull('performance_plan_id')->where('status', '!=', 'cancelled')->count(),
                PlanItem::with('employee:id,name')->whereNull('performance_plan_id')->where('status', '!=', 'cancelled')->limit(self::SAMPLE)->get()->map(fn (PlanItem $i) => ['Kegiatan' => $i->description, 'Pegawai' => $i->employee?->name])->all()),
            $this->check('rk_no_team', 'RK tanpa tim', 'RK tanpa tim tidak muncul di rekap tim mana pun.', 'Sinkronkan RK dari kipApp, atau tetapkan timnya di halaman Rencana Kinerja.', 'medium',
                PerformancePlan::whereNull('team_id')->whereNull('project_id')->count(),
                PerformancePlan::whereNull('team_id')->whereNull('project_id')->limit(self::SAMPLE)->get(['description'])->map(fn ($p) => ['RK' => $p->description])->all()),
            $this->check('projects_no_leader_rk', 'Projek tanpa RK Ketua', 'Tanpa RK Ketua, RK anggota tidak bisa ditautkan otomatis ke projek ini.', 'Sinkronkan struktur dari kipApp.', 'medium',
                Project::whereNull('leader_rk')->count(),
                Project::with('team:id,name')->whereNull('leader_rk')->limit(self::SAMPLE)->get()->map(fn (Project $p) => ['Projek' => $p->name, 'Tim' => $p->team?->name])->all()),
            $this->check('employees_no_kip_id', 'Pegawai tanpa ID kipApp', 'Kegiatan pegawai ini tidak bisa dikirim atau dibaca dari kipApp.', 'Sinkronkan struktur supaya ID pegawai terisi.', 'medium',
                Employee::where('is_active', true)->whereNull('kip_pegawai_id')->count(),
                Employee::where('is_active', true)->whereNull('kip_pegawai_id')->limit(self::SAMPLE)->get(['name'])->map(fn ($e) => ['Pegawai' => $e->name])->all()),
            $this->check('teams_no_leader', 'Tim tanpa ketua', 'Tanpa ketua, tim tidak punya PJ yang menulis parafrase.', 'Sinkronkan struktur, atau tetapkan ketua di halaman Tim Kerja.', 'medium',
                Team::where('is_active', true)->whereNull('leader_id')->count(),
                Team::where('is_active', true)->whereNull('leader_id')->limit(self::SAMPLE)->get(['name'])->map(fn ($t) => ['Tim' => $t->name])->all()),
            $this->check('push_failed', 'Pengiriman ke kipApp gagal', 'Kegiatan ini sudah dicoba dikirim tetapi ditolak atau gagal. Pesan kegagalannya tercatat.', 'Baca pesannya, perbaiki sumbernya, lalu kirim ulang dari detail kegiatan.', 'high',
                PlanItem::whereNotNull('push_error')->whereNull('kip_external_id')->count(),
                PlanItem::with('employee:id,name')->whereNotNull('push_error')->whereNull('kip_external_id')->latest('updated_at')->limit(self::SAMPLE)->get()->map(fn (PlanItem $i) => ['Kegiatan' => $i->description, 'Pegawai' => $i->employee?->name, 'Pesan' => $i->push_error])->all()),
        ];
    }

    /** @param  list<array<string, mixed>>  $rows */
    private function check(string $key, string $title, string $why, string $fix, string $severity, int $count, array $rows): array
    {
        return compact('key', 'title', 'why', 'fix', 'severity', 'count', 'rows');
    }
}
