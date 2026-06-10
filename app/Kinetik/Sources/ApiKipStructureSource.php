<?php

namespace App\Kinetik\Sources;

use App\Kinetik\Contracts\KipAuthenticator;
use App\Kinetik\Contracts\KipStructureSource;
use App\Kinetik\Data\KipMemberData;
use App\Kinetik\Data\KipProjectData;
use App\Kinetik\Data\KipTeamData;
use App\Kinetik\Exceptions\KipApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ApiKipStructureSource implements KipStructureSource
{
    public function __construct(
        private readonly KipAuthenticator $authenticator,
    ) {}

    /**
     * Enumerate every team of the configured unit kerja via the office-wide
     * team directory (monitoring/hirarki/daerah). Returns all teams, not just
     * the admin's own (unlike pegawai/lokasi).
     */
    public function fetchTeams(): Collection
    {
        $response = $this->client()->get('v1/monitoring/hirarki/daerah', [
            'unitkerjaid' => config('kinetik.kip.unitkerja_id'),
            'wilayahid' => config('kinetik.kip.wilayah_id'),
            'periodeid' => config('kinetik.kip.periode_id'),
        ]);

        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, 'fetchTeams');
        }

        $body = $response->json();

        if (! is_array($body)) {
            return collect();
        }

        return collect($body['data'] ?? [])
            ->map(fn (array $row): KipTeamData => KipTeamData::fromApiRow($row))
            ->filter(fn (KipTeamData $t): bool => $t->externalId !== '')
            ->unique(fn (KipTeamData $t): string => $t->externalId)
            ->values();
    }

    public function fetchTeamProjects(string $timkerjaId): Collection
    {
        $response = $this->client()->get('v1/proyek', ['timkerjaid' => $timkerjaId]);

        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, 'fetchTeamProjects');
        }

        $rows = $response->json();

        if (! is_array($rows)) {
            return collect();
        }

        return collect($rows)
            ->map(fn (array $row): KipProjectData => KipProjectData::fromApiRow($row))
            ->filter(fn (KipProjectData $p): bool => $p->externalId !== '')
            ->values();
    }

    public function fetchTeamMembers(string $timkerjaId): Collection
    {
        $response = $this->client()->get('v1/timkerja/anggota', ['id' => $timkerjaId]);

        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, 'fetchTeamMembers');
        }

        $rows = $response->json();

        if (! is_array($rows)) {
            return collect();
        }

        return collect($rows)
            ->map(fn (array $row): KipMemberData => KipMemberData::fromApiRow($row))
            ->filter(fn (KipMemberData $m): bool => $m->nipLama !== '')
            ->values();
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl(config('kinetik.kip.base_url'))
            ->timeout(config('kinetik.kip.timeout'))
            ->acceptJson();

        return $this->authenticator->apply($request);
    }
}
