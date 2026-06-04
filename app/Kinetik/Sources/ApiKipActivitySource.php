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
     * Fetch unsent activities for the given NIP via the two-step kipApp flow:
     *
     *  1. GET /v1/dashboard/kegiatanpegawai/belumkirim?niplama=<niplama>
     *     Returns an array of period/SKP groups; collect every skpid from
     *     group.kegiatan[] where jumlahkegiatan > 0.
     *
     *  2. For each skpid: GET /v1/kegiatan?skpid=<skpid>
     *     Returns per-day activity rows. Keep only rows where tanggalkirim === null
     *     (those are the truly unsent ones).
     */
    public function fetchUnsentActivities(string $nipLama): Collection
    {
        $response = $this->client()
            ->get('v1/dashboard/kegiatanpegawai/belumkirim', ['niplama' => $nipLama]);

        if (! $response->successful()) {
            throw KipApiException::fromResponse($response, 'fetchUnsentActivities');
        }

        $groups = $response->json();

        if (! is_array($groups)) {
            return collect();
        }

        $skpIds = collect($groups)
            ->flatMap(function (array $group): array {
                $kegiatan = $group['kegiatan'] ?? [];

                return collect($kegiatan)
                    ->filter(fn (array $k): bool => ($k['jumlahkegiatan'] ?? 0) > 0)
                    ->pluck('skpid')
                    ->filter()
                    ->values()
                    ->all();
            });

        return $skpIds
            ->flatMap(fn (string $skpId): Collection => $this->fetchActivitiesBySkp($skpId))
            ->filter(fn (KipActivityData $dto): bool => $dto->sentAt === null)
            ->values();
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
