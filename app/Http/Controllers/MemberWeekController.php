<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\BuildMemberPeriodRowsAction;
use App\Actions\Kinetik\BuildMemberWeekAction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Rekap Mingguan": the signed-in member plans and monitors their own week. Nobody else's rows are sent. */
class MemberWeekController extends Controller
{
    public function index(Request $request, BuildMemberWeekAction $week, BuildMemberPeriodRowsAction $periods): Response
    {
        $employee = $request->user()->employee;

        // No week in the address: the months of a year, then the weeks of a month, then one week.
        if ($request->query('week') === null) {
            $year = (int) $request->query('year', now()->year);
            $month = $request->query('month') !== null ? (int) $request->query('month') : null;

            return Inertia::render('Kinetik/RekapMingguan', [
                'employee' => $employee ? ['id' => $employee->id, 'name' => $employee->display_name ?? $employee->name] : null,
                'canSendToAtasan' => (bool) config('kinetik.kip.send_to_atasan'),
                'skpYear' => (int) config('kinetik.kip.tahun'),
                'view' => $month === null ? 'months' : 'weeks',
                'periods' => $employee ? ($month === null ? $periods->months($employee, $year) : $periods->weeks($employee, $year, $month)) : [],
                'year' => $year,
                'month' => $month,
            ]);
        }

        $anchor = Carbon::parse($request->query('week'));

        return Inertia::render('Kinetik/RekapMingguan', [
            'view' => 'detail',
            'periods' => [],
            'year' => $anchor->year,
            'month' => $anchor->month,
            'employee' => $employee ? ['id' => $employee->id, 'name' => $employee->display_name ?? $employee->name] : null,
            'canSendToAtasan' => (bool) config('kinetik.kip.send_to_atasan'),
            'skpYear' => (int) config('kinetik.kip.tahun'),
            ...($employee ? $week->execute($employee, $anchor) : []),
        ]);
    }
}
