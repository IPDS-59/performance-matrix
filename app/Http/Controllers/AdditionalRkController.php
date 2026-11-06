<?php

namespace App\Http\Controllers;

use App\Models\PerformancePlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A member adds an RK tambahan to their own list, so extra work outside the team's RK has a place to be filed. */
class AdditionalRkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $data = $request->validate(['description' => ['required', 'string', 'min:10', 'max:500']]);
        $text = trim(preg_replace('/\s+/', ' ', $data['description']));

        $plan = PerformancePlan::where('owner_employee_id', $employee->id)->get()
            ->first(fn (PerformancePlan $p) => mb_strtolower(preg_replace('/\s+/', ' ', trim($p->description))) === mb_strtolower($text))
            ?? PerformancePlan::create([
                'description' => $text,
                'team_id' => $employee->teams()->orderByDesc('employee_team.is_primary')->value('teams.id'),
                'is_additional' => true,
                'owner_employee_id' => $employee->id,
            ]);

        return response()->json(['id' => $plan->id, 'description' => $plan->description]);
    }
}
