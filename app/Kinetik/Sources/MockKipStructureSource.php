<?php

namespace App\Kinetik\Sources;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipMemberData;
use App\Kinetik\Data\KipOfficeData;
use App\Kinetik\Data\KipPositionData;
use App\Kinetik\Data\KipProjectData;
use App\Kinetik\Data\KipRatingData;
use App\Kinetik\Data\KipRkData;
use App\Kinetik\Data\KipSkpTree;
use App\Kinetik\Data\KipTeamData;
use Illuminate\Support\Collection;

/**
 * Hard-coded fixture structure so the sync pipeline can run without a live
 * kipApp connection. Activate with KIP_SOURCE=mock.
 *
 * Shape: 2 teams, each with 2 projects, each project with 2 members.
 */
class MockKipStructureSource implements KipStructureSource
{
    public function fetchTeams(): Collection
    {
        return collect([
            KipTeamData::fromApiRow(['id' => '900001', 'namaTim' => 'TIM MOCK A']),
            KipTeamData::fromApiRow(['id' => '900002', 'namaTim' => 'TIM MOCK B']),
        ]);
    }

    public function fetchTeamProjects(string $timkerjaId): Collection
    {
        return collect([1, 2])->map(fn (int $i) => KipProjectData::fromApiRow([
            'timkerjaid' => $timkerjaId,
            'namatim' => "TIM MOCK {$timkerjaId}",
            'niplamaketua' => "34000{$timkerjaId}1",
            'namaketua' => 'Ketua Mock',
            'proyekid' => "{$timkerjaId}-proj-{$i}",
            'namaproyek' => "Projek Mock {$timkerjaId}-{$i}",
            'anggota' => [
                ['anggotaid' => "{$timkerjaId}-{$i}-a", 'pegawaiid' => '1', 'niplama' => "34000{$timkerjaId}1", 'nipbaru' => '1', 'nama' => 'Anggota Satu', 'jabatanid' => '50', 'namajabatan' => 'Statistisi'],
                ['anggotaid' => "{$timkerjaId}-{$i}-b", 'pegawaiid' => '2', 'niplama' => "34000{$timkerjaId}2", 'nipbaru' => '2', 'nama' => 'Anggota Dua', 'jabatanid' => '50', 'namajabatan' => 'Statistisi'],
            ],
        ]));
    }

    public function fetchEmployeeOffices(string $nipLama): array
    {
        // Mock staff all work at the configured office.
        return [new KipOfficeData(
            (string) config('kinetik.kip.wilayah_id'), 'Sulawesi Tengah',
            (string) config('kinetik.kip.unitkerja_id'), 'BPS Provinsi',
        )];
    }

    public function fetchPositionHistory(string $nipLama): array
    {
        return [
            new KipPositionData("{$nipLama}-1", '2023-01-02', 'Statistisi Ahli Pertama', 'III/a', 'Penata Muda'),
            new KipPositionData("{$nipLama}-2", '2025-04-01', 'Statistisi Ahli Pertama', 'III/b', 'Penata Muda Tk. I'),
        ];
    }

    public function fetchPeriodicRatings(string $pegawaiId): array
    {
        return [
            new KipRatingData("{$pegawaiId}-q1", '2026-01-01', '2026-03-31', 'Statistisi Ahli Pertama', 'Baik', 100.0, 'Dinilai'),
            new KipRatingData("{$pegawaiId}-q2", '2026-04-01', '2026-06-30', 'Statistisi Ahli Pertama', 'Sangat Baik', 110.0, 'Dinilai'),
        ];
    }

    public function fetchSkpTree(string $pegawaiId): ?KipSkpTree
    {
        // Mock kepala (pegawaiid "kepala") holds the PK; everyone else is a ketua under it.
        if ($pegawaiId === 'kepala') {
            return new KipSkpTree('skp-kepala', null, collect([
                KipRkData::fromApiRow(['rkid' => 'pk1', 'rencanakinerja' => 'Terwujudnya Penyediaan Data Statistik', 'iscopypk' => 1]),
            ]), collect(['pk1' => [['ikiid' => 'iku1', 'rkid' => 'pk1', 'iki' => 'Persentase Publikasi Statistik yang Berkualitas: 100%']]]));
        }

        return new KipSkpTree("skp-{$pegawaiId}", 'kepala', collect([
            KipRkData::fromApiRow(['rkid' => "{$pegawaiId}-k1", 'rencanakinerja' => 'RK Ketua Contoh', 'rencanakinerjaatasan' => 'Terwujudnya Penyediaan Data Statistik']),
        ]), collect());
    }

    public function fetchTeamMembers(string $timkerjaId): Collection
    {
        return collect([
            KipMemberData::fromApiRow(['anggotaid' => "{$timkerjaId}-m1", 'pegawaiid' => '1', 'niplama' => "34000{$timkerjaId}1", 'nipbaru' => '1', 'nama' => 'Anggota Satu', 'jabatanid' => '50', 'namajabatan' => 'Statistisi']),
            KipMemberData::fromApiRow(['anggotaid' => "{$timkerjaId}-m2", 'pegawaiid' => '2', 'niplama' => "34000{$timkerjaId}2", 'nipbaru' => '2', 'nama' => 'Anggota Dua', 'jabatanid' => '50', 'namajabatan' => 'Statistisi']),
        ]);
    }

    public function fetchEmployeePlans(string $nipLama): Collection
    {
        return collect([
            KipRkData::fromApiRow(
                ['rkid' => "mock-rk-{$nipLama}-001", 'rencanakinerja' => 'Tersusunnya publikasi statistik', 'timkerjaid' => '900001'],
                'Jumlah Publikasi Sebanyak 4 dokumen',
            ),
            KipRkData::fromApiRow(
                ['rkid' => "mock-rk-{$nipLama}-002", 'rencanakinerja' => 'Terlaksananya supervisi', 'timkerjaid' => '900001'],
                'Persentase Supervisi: 100%',
            ),
        ]);
    }
}
