<?php

namespace App\Http\Controllers;

use App\Models\PerformanceReport;
use App\Models\PerformanceReportReview;
use App\Models\Project;
use App\Notifications\ReportApprovedNotification;
use App\Notifications\ReportRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PerformanceApprovalController extends Controller
{
    public function approve(Request $request, PerformanceReport $report): RedirectResponse
    {
        $this->authorizeReview($request, $report);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $report->update([
            'approval_status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'] ?? null,
        ]);

        PerformanceReportReview::create([
            'performance_report_id' => $report->id,
            'actor_id' => $request->user()->id,
            'action' => 'approved',
            'note' => $validated['review_note'] ?? null,
        ]);

        if ($report->reporter?->user) {
            $report->reporter->user->notify(new ReportApprovedNotification($report, $request->user()));
        }

        return back()->with('success', 'Laporan berhasil disetujui.');
    }

    public function reject(Request $request, PerformanceReport $report): RedirectResponse
    {
        $this->authorizeReview($request, $report);

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:1000'],
        ]);

        $report->update([
            'approval_status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $validated['review_note'],
        ]);

        PerformanceReportReview::create([
            'performance_report_id' => $report->id,
            'actor_id' => $request->user()->id,
            'action' => 'rejected',
            'note' => $validated['review_note'],
        ]);

        if ($report->reporter?->user) {
            $report->reporter->user->notify(new ReportRejectedNotification($report, $request->user()));
        }

        return back()->with('success', 'Laporan ditolak.');
    }

    private function authorizeReview(Request $request, PerformanceReport $report): void
    {
        $user = $request->user();

        // Head can review any report
        if ($user->hasRole('head') || $user->hasRole('admin')) {
            return;
        }

        // Staff can review if they are the project leader for this report's work item
        $isLead = Project::where('leader_id', $user->employee?->id)
            ->whereHas('workItems', fn ($q) => $q->where('id', $report->work_item_id))
            ->exists();

        abort_unless($isLead, 403);
    }
}
