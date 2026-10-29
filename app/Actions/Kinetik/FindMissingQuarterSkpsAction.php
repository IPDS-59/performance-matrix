<?php

namespace App\Actions\Kinetik;

use App\Kinetik\Exceptions\KipApiException;
use App\Kinetik\Sources\ApiKipWriter;
use App\Models\PlanItem;
use App\Models\User;
use App\Services\Kinetik\MemberKipToken;
use Carbon\Carbon;
use RuntimeException;

/**
 * Quarters the member works in (the current one, and any with a plan not yet sent) that have no SKP in kipApp.
 * A new quarter has none until someone creates it; the sync used to say "done" and never mentioned it.
 */
class FindMissingQuarterSkpsAction
{
    public function __construct(private readonly MemberKipToken $tokens) {}

    /** @return list<int> quarters, 1 to 4; empty when nothing is missing or kipApp cannot be asked */
    public function execute(User $user): array
    {
        $employee = $user->employee;
        if ($employee === null || $employee->kip_pegawai_id === null || config('kinetik.kip.source') === 'mock') {
            return [];
        }

        $tahun = (int) config('kinetik.kip.tahun');
        $needed = PlanItem::where('employee_id', $employee->id)->where('status', 'planned')
            ->whereYear('date_start', $tahun)->pluck('date_start')
            ->map(fn ($d) => Carbon::parse($d)->quarter)->push(now(config('app.schedule_timezone', 'Asia/Makassar'))->quarter)
            ->unique()->sort()->values();

        try {
            $have = array_keys((new ApiKipWriter($this->tokens->for($user)))->quarterSkps((string) $employee->kip_pegawai_id, (int) config('kinetik.kip.periode_id')));
        } catch (RuntimeException|KipApiException) {
            return [];
        }

        return $needed->reject(fn (int $q) => in_array($q, $have, true))->values()->all();
    }
}
