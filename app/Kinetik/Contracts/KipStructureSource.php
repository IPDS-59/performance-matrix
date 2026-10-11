<?php

namespace App\Kinetik\Contracts;

use App\Kinetik\Data\KipMemberData;
use App\Kinetik\Data\KipOfficeData;
use App\Kinetik\Data\KipPositionData;
use App\Kinetik\Data\KipProjectData;
use App\Kinetik\Data\KipRatingData;
use App\Kinetik\Data\KipRkData;
use App\Kinetik\Data\KipSkpTree;
use App\Kinetik\Data\KipTeamData;
use Illuminate\Support\Collection;

interface KipStructureSource
{
    /**
     * Enumerate all work teams (timkerja) of the configured unit kerja, via the
     * monitoring/hirarki/daerah directory endpoint.
     *
     * @return Collection<int, KipTeamData>
     */
    public function fetchTeams(): Collection;

    /**
     * Fetch one team's projects, including the team leader and each project's
     * members (proyek?timkerjaid=).
     *
     * @return Collection<int, KipProjectData>
     */
    public function fetchTeamProjects(string $timkerjaId): Collection;

    /**
     * Fetch one team's members (timkerja/anggota?id=).
     *
     * @return Collection<int, KipMemberData>
     */
    public function fetchTeamMembers(string $timkerjaId): Collection;

    /**
     * Where an employee works now (v1/pegawai/lokasi). Empty when unknown.
     *
     * @return list<KipOfficeData>
     */
    public function fetchEmployeeOffices(string $nipLama): array;

    /**
     * Cascade: an employee's RK list with targets parsed from IKI text
     * (belumkirim -> skp/rk -> skp/iki).
     *
     * @return Collection<int, KipRkData>
     */
    public function fetchEmployeePlans(string $nipLama): Collection;

    /**
     * Position history of an employee (v1/pegawai?niplama=), one record per
     * kipApp pegawaiid, with tmt, jabatan and golongan.
     *
     * @return list<KipPositionData>
     */
    public function fetchPositionHistory(string $nipLama): array;

    /**
     * Every periodic SKP rating of one pegawaiid, all years (v1/skp?jenis=2).
     *
     * @return list<KipRatingData>
     */
    public function fetchPeriodicRatings(string $pegawaiId): array;

    /**
     * The employee's yearly SKP for the configured period with its RK and each
     * RK's IKI (v1/skp jenis=1, v1/skp/rk, v1/skp/iki). Null when there is none.
     */
    public function fetchSkpTree(string $pegawaiId): ?KipSkpTree;
}
