<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\BuildMemberWeekAction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Rekap Mingguan": the signed-in member plans and monitors their own week. Nobody else's rows are sent. */
class MemberWeekController extends Controller
{
    public function index(Request $request, BuildMemberWeekAction $week): Response
    {
        $employee = $request->user()->employee;
        $anchor = Carbon::parse($request->query('week', now()->toDateString()));

        return Inertia::render('Kinetik/RekapMingguan', [
            'employee' => $employee ? ['id' => $employee->id, 'name' => $employee->display_name ?? $employee->name] : null,
            'canSendToAtasan' => (bool) config('kinetik.kip.send_to_atasan'),
            ...($employee ? $week->execute($employee, $anchor) : []),
        ]);
    }
}
