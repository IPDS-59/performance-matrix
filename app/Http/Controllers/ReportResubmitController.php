<?php

namespace App\Http\Controllers;

use App\Models\PerformanceReport;
use App\Models\PerformanceReportReview;
use App\Models\WorkItemAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReportResubmitController extends Controller
{
    public function store(Request $request, PerformanceReport $report): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if(! $employee, 403, 'Akun belum terhubung ke data pegawai.');
        abort_if($report->reported_by !== $employee->id, 403);
        abort_if($report->approval_status !== 'rejected', 422, 'Hanya laporan yang ditolak yang dapat diajukan ulang.');

        $validated = $request->validate([
            'realization' => ['required', 'numeric', 'min:0'],
            'issues' => ['nullable', 'string'],
            'solutions' => ['nullable', 'string'],
            'action_plan' => ['nullable', 'string'],
        ]);

        $assignment = WorkItemAssignment::where('work_item_id', $report->work_item_id)
            ->where('employee_id', $employee->id)
            ->first();

        $target = (float) ($assignment?->target ?? $report->workItem->target);

        $otherMonthsTotal = (float) PerformanceReport::where('work_item_id', $report->work_item_id)
            ->where('reported_by', $employee->id)
            ->where(function ($q) use ($report) {
                $q->where('period_year', '!=', $report->period_year)
                    ->orWhere('period_month', '!=', $report->period_month);
            })
            ->sum('realization');

        $achievementPercentage = $target > 0
            ? min(100, round(($otherMonthsTotal + (float) $validated['realization']) / $target * 100, 2))
            : 0;

        $report->update([
            'realization' => $validated['realization'],
            'achievement_percentage' => $achievementPercentage,
            'issues' => $validated['issues'] ?? null,
            'solutions' => $validated['solutions'] ?? null,
            'action_plan' => $validated['action_plan'] ?? null,
            'approval_status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ]);

        PerformanceReportReview::create([
            'performance_report_id' => $report->id,
            'actor_id' => $request->user()->id,
            'action' => 'resubmitted',
        ]);

        return back()->with('success', 'Laporan berhasil diajukan ulang.');
    }
}
