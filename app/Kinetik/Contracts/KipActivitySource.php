<?php

namespace App\Kinetik\Contracts;

use App\Kinetik\Data\KipActivityData;
use App\Kinetik\Data\KipPlanData;
use Illuminate\Support\Collection;

interface KipActivitySource
{
    /**
     * Fetch unsent daily activities for an employee.
     *
     * @param  string  $nipLama  Legacy 9-digit NIP (niplama)
     * @return Collection<int, KipActivityData>
     */
    public function fetchUnsentActivities(string $nipLama): Collection;

    /**
     * Fetch Rencana Kinerja (RK) list for an employee.
     *
     * @param  string  $nipLama  Legacy 9-digit NIP (niplama)
     * @return Collection<int, KipPlanData>
     */
    public function fetchPlans(string $nipLama): Collection;
}
