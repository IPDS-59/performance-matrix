<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\BuildMemberWeekAction;
use App\Models\MemberSkpStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Kirim ke Atasan" of a quarter, like kipApp's SKP triwulan page: every kegiatan of the quarter, by RK, with the ones not sent
 * checked off in one go. Sending and taking back go through the same endpoints as the weekly table.
 */
class QuarterSendController extends Controller
{
    public function index(Request $request, BuildMemberWeekAction $week): Response
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $year = (int) config('kinetik.kip.tahun');
        $default = now(config('app.schedule_timezone', 'Asia/Makassar'))->year === $year ? (int) ceil(now(config('app.schedule_timezone', 'Asia/Makassar'))->month / 3) : 1;
        $quarter = min(4, max(1, (int) $request->query('quarter', $default)));
        $from = Carbon::create($year, ($quarter - 1) * 3 + 1, 1);

        return Inertia::render('Kinetik/QuarterSend', [
            'year' => $year,
            'quarter' => $quarter,
            'rows' => $week->rowsBetween($employee, $from->toDateString(), $from->copy()->endOfQuarter()->toDateString()),
            'canSendToAtasan' => (bool) config('kinetik.kip.send_to_atasan'),
            'skpStatus' => MemberSkpStatus::where(['employee_id' => $employee->id, 'tahun' => $year, 'quarter' => $quarter])->value('status_text'),
        ]);
    }
}
