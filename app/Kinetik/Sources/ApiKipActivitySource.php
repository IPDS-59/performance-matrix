<?php

namespace App\Kinetik\Sources;

use App\Kinetik\Contracts\KipActivitySource;
use App\Kinetik\Contracts\KipAuthenticator;
use App\Kinetik\Data\KipActivityData;
use App\Kinetik\Data\KipPlanData;
use App\Kinetik\Exceptions\KipApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ApiKipActivitySource implements KipActivitySource
{
    public function __construct(
        private readonly KipAuthenticator $authenticator,
    ) {}

    /**
     * Fetch all daily activities (submitted + unsent) for the given NIP.
     *
     * SKP IDs are discovered from the UNION of two sources so that employees
     * who have already submitted everything are still covered:
     *
     *  1. GET /v1/dashboard/rkpegawai?niplama=<niplama>  (state-independent)
     *     Returns [{rk:[{skpid:...}]}]; extracts group['rk'][]['skpid'].
     *
     *  2. GET /v1/dashboard/kegiatanpegawai/belumkirim?niplama=<niplama>
     *     Returns groups of unsubmitted activity buckets; extracts skpid from
     *     group['kegiatan'][] where jumlahkegiatan > 0.
     *     Kept so freshly-created activities not yet in rkpegawai are covered.
     *
     * The two sets are union-ed and de-duplicated. For each skpid,
     * GET /v1/kegiatan?skpid=<skpid> is called and ALL rows (sent + unsent)
     * are returned — no filtering on sentAt.
     */
    public function fetchActivities(string $nipLama): Collection
    {
        // Source 1: rkpegawai (state-independent)
        $rkResponse = $this->client()
            ->get('v1/dashboard/rkpegawai', ['niplama' => $nipLama]);

        if (! $rkResponse->successful()) {
            throw KipApiException::fromResponse($rkResponse, 'fetchActivities');
        }

        $rkSkpIds = collect($this->asGroups($rkResponse->json()))
            ->flatMap(function (array $group): array {
                $rk = $group['rk'] ?? [];

                return collect($rk)
                    ->pluck('skpid')
                    ->filter()
                    ->values()
                    ->all();
            });

        // Source 2: belumkirim (catches freshly-created activities not yet in rkpegawai)
        $belumKirimResponse = $this->client()
            ->get('v1/dashboard/kegiatanpegawai/belumkirim', ['niplama' => $nipLama]);

        if (! $belumKirimResponse->successful()) {
            throw KipApiException::fromResponse($belumKirimResponse, 'fetchActivities');
        }

        $belumKirimSkpIds = collect($this->asGroups($belumKirimResponse->json()))
            ->flatMap(function (array $group): array {
                $kegiatan = $group['kegiatan'] ?? [];

                return collect($kegiatan)
                    ->filter(fn (array $k): bool => ($k['jumlahkegiatan'] ?? 0) > 0)
                    ->pluck('skpid')
                    ->filter()
                    ->values()
                    ->all();
            });

        $skpIds = $rkSkpIds
            ->merge($belumKirimSkpIds)
            ->filter()
            ->unique()
            ->values();

        return $skpIds
            ->flatMap(fn (string $skpId): Collection => $this->fetchActivitiesBySkp($skpId))
            ->values();
    }

    /**
     * Normalise a kipApp "groups" response into a list of group arrays.
     *
     * Some dashboard endpoints return a LIST of group objects when there is data
     * (`[{rk:[...]}]`) but a single associative object when empty
     * (`{jumlahrk:0}`). Wrap the object case so callers can always iterate
     * arrays; non-array / scalar payloads collapse to an empty list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function asGroups(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }

        $groups = array_is_list($json) ? $json : [$json];

        return array_values(array_filter($groups, 'is_array'));
    }

    /**
     * Fetch all per-day activity rows for a single SKP ID and map them to DTOs.
     * Does NOT filter by tanggalkirim — callers decide what to keep.
     */
    public function fetchActivitiesBySkp(string $skpId): Collection
    {
        $response = $this->client()
            ->get('v1/kegiatan', ['skpid' => $skpId]);

        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, 'fetchActivitiesBySkp');
        }

        $rows = $response->json();

        if (! is_array($rows)) {
            return collect();
        }

        return collect($rows)->map(fn (array $row) => KipActivityData::fromApiRow($row));
    }

    /**
     * Fetch RK (rencana kinerja) plans for the given NIP.
     *
     * NOTE: The populated shape of this endpoint is still unconfirmed — it returned
     * `{jumlahrk:0}` during the 2026-06-04 live capture. KipPlanData uses defensive
     * candidate-key mapping until the shape is confirmed.
     */
    public function fetchPlans(string $nipLama): Collection
    {
        $response = $this->client()
            ->get('v1/dashboard/rkpegawai', ['niplama' => $nipLama]);

        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, 'fetchPlans');
        }

        $rows = $response->json();

        if (! is_array($rows)) {
            return collect();
        }

        return collect($rows)->map(fn (array $row) => KipPlanData::fromApiRow($row));
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl(config('kinetik.kip.base_url'))
            ->timeout(config('kinetik.kip.timeout'))
            ->acceptJson();

        return $this->authenticator->apply($request);
    }
}
