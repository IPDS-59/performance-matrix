<?php

namespace App\Kinetik\Sources;

use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipMemberData;
use App\Kinetik\Data\KipProjectData;
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

    public function fetchTeamMembers(string $timkerjaId): Collection
    {
        return collect([
            KipMemberData::fromApiRow(['anggotaid' => "{$timkerjaId}-m1", 'pegawaiid' => '1', 'niplama' => "34000{$timkerjaId}1", 'nipbaru' => '1', 'nama' => 'Anggota Satu', 'jabatanid' => '50', 'namajabatan' => 'Statistisi']),
            KipMemberData::fromApiRow(['anggotaid' => "{$timkerjaId}-m2", 'pegawaiid' => '2', 'niplama' => "34000{$timkerjaId}2", 'nipbaru' => '2', 'nama' => 'Anggota Dua', 'jabatanid' => '50', 'namajabatan' => 'Statistisi']),
        ]);
    }
}
