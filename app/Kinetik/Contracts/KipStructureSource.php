<?php

namespace App\Kinetik\Contracts;

use App\Kinetik\Data\KipMemberData;
use App\Kinetik\Data\KipProjectData;
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
}
