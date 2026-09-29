<?php

namespace App\Kinetik\Contracts;

use App\Kinetik\Data\KipActivityData;
use App\Kinetik\Data\KipPlanData;
use App\Kinetik\Data\KipRkData;
use Illuminate\Support\Collection;

interface KipActivitySource
{
    /**
     * Fetch all daily activities (submitted + unsent) for an employee.
     *
     * @param  string  $nipLama  Legacy 9-digit NIP (niplama)
     * @param  string|null  $pegawaiId  kipApp internal employee id; enables discovery of every periodic (quarterly) SKP
     * @return Collection<int, KipActivityData>
     */
    public function fetchActivities(string $nipLama, ?string $pegawaiId = null): Collection;

    /**
     * Fetch Rencana Kinerja (RK) list for an employee.
     *
     * @param  string  $nipLama  Legacy 9-digit NIP (niplama)
     * @return Collection<int, KipPlanData>
     */
    public function fetchPlans(string $nipLama): Collection;

    /**
     * Every RK of the employee's yearly SKP for the configured period
     * (v1/skp jenis=1, then v1/skp/rk). Each row carries the leader RK it is
     * cascaded from (rencanakinerjaatasan).
     *
     * @return Collection<int, KipRkData>
     */
    public function fetchYearlyRks(string $pegawaiId): Collection;
}
