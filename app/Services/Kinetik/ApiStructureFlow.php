<?php

namespace App\Services\Kinetik;

use App\Kinetik\Contracts\StructureFlow;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use RuntimeException;

/**
 * StructureFlow against the live kipApp. kipApp does not return the id of a created row, so each create
 * lists the rows again and finds the new one by its text.
 */
class ApiStructureFlow implements StructureFlow
{
    public function __construct(private readonly MemberKipToken $tokens) {}

    public function rkKetua(User $user, Team $team): array
    {
        $rows = $this->writer($user)->rkKetuaList($this->timKerjaId($team));

        return array_map(fn (array $r) => ['id' => (string) $r['rkketuaid'], 'text' => (string) ($r['rencanakinerjaketua'] ?? '')], $rows);
    }

    public function ikiJpt(User $user): array
    {
        return array_map(fn (array $r) => ['id' => (string) $r['proyekid'], 'text' => (string) ($r['namaproyek'] ?? '')], $this->writer($user)->level1Proyek());
    }

    public function createRkKetua(User $user, Team $team, string $text, string $ikiJptId): string
    {
        $writer = $this->writer($user);
        $timId = $this->timKerjaId($team);
        $ketua = $team->leader?->kip_pegawai_id;
        if ($ketua === null) {
            throw new RuntimeException('Ketua tim ini belum terhubung ke data pegawai kipApp.');
        }

        $writer->createRkKetua($timId, $text, $ikiJptId, (string) $ketua);

        $found = collect($writer->rkKetuaList($timId))->first(fn (array $r) => $this->same($r['rencanakinerjaketua'] ?? '', $text));
        if ($found === null) {
            throw new RuntimeException('RK Ketua dikirim, tetapi belum terlihat di kipApp. Muat ulang daftarnya.');
        }

        return (string) $found['rkketuaid'];
    }

    public function pushProject(User $user, Project $project, string $rkKetuaId): void
    {
        $team = $project->team;
        $timId = $this->timKerjaId($team);
        $writer = $this->writer($user);

        $members = $project->members()->get()->filter(fn ($e) => $e->kip_pegawai_id !== null)
            ->map(fn ($e) => ['pegawaiid' => (string) $e->kip_pegawai_id, 'isadmin' => 0])->values()->all();
        if ($members === []) {
            throw new RuntimeException('Proyek ini belum punya anggota yang terhubung ke kipApp. kipApp meminta minimal satu anggota.');
        }

        $writer->createProyek($project->name, $rkKetuaId, $members);

        $found = collect($writer->proyekList($timId))->first(fn (array $r) => (string) ($r['rkketuaid'] ?? '') === $rkKetuaId && $this->same($r['namaproyek'] ?? '', $project->name));
        if ($found === null) {
            throw new RuntimeException('Proyek dikirim, tetapi belum terlihat di kipApp. Muat ulang daftarnya.');
        }
        $project->forceFill(['kip_external_id' => (string) $found['proyekid']])->save();
    }

    public function pushPlan(User $user, PerformancePlan $plan): void
    {
        $project = $plan->project;
        $pic = $plan->pic;
        if ($project?->kip_external_id === null) {
            throw new RuntimeException('Kirim proyeknya ke kipApp dulu.');
        }
        if ($pic?->kip_pegawai_id === null) {
            throw new RuntimeException('RK Anggota butuh PIC yang terhubung ke data pegawai kipApp.');
        }

        $writer = $this->writer($user);
        $timId = $this->timKerjaId($project->team);
        $proyek = collect($writer->proyekList($timId))->first(fn (array $r) => (string) $r['proyekid'] === (string) $project->kip_external_id);
        $member = collect($proyek['anggota'] ?? [])->first(fn (array $a) => (string) ($a['pegawaiid'] ?? '') === (string) $pic->kip_pegawai_id);
        if ($member === null) {
            throw new RuntimeException('PIC bukan anggota proyek ini di kipApp.');
        }

        $writer->createRkAnggota((string) $member['anggotaid'], $plan->description, (string) $project->kip_external_id);

        $found = collect($writer->rkProyekList($timId, (string) $project->kip_external_id))
            ->first(fn (array $r) => (string) ($r['anggotaid'] ?? '') === (string) $member['anggotaid'] && $this->same($r['rencanakinerja'] ?? '', $plan->description));
        if ($found === null) {
            throw new RuntimeException('RK Anggota dikirim, tetapi belum terlihat di kipApp. Muat ulang daftarnya.');
        }
        $plan->forceFill(['kip_rkproyek_id' => (string) $found['rkproyekid']])->save();
    }

    private function writer(User $user): ApiKipWriter
    {
        return new ApiKipWriter($this->tokens->for($user));
    }

    private function timKerjaId(?Team $team): string
    {
        if ($team?->kip_external_id === null) {
            throw new RuntimeException('Tim ini belum terhubung ke kipApp.');
        }

        return (string) $team->kip_external_id;
    }

    private function same(mixed $a, string $b): bool
    {
        $norm = fn ($t) => mb_strtolower(trim(preg_replace('/\s+/', ' ', (string) $t)));

        return $norm($a) === $norm($b);
    }
}
