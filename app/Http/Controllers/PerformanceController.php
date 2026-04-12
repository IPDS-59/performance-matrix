<?php

namespace App\Http\Controllers;

use App\Actions\Performance\SavePerformanceBatchAction;
use App\Models\PerformanceReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerformanceController extends Controller
{
    public function storeBatch(Request $request, SavePerformanceBatchAction $action): RedirectResponse
    {
        $this->authorize('create', PerformanceReport::class);

        $employee = $request->user()->employee;
        abort_if(! $employee, 403);

        $validated = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', 'min:2020'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.work_item_id' => ['required', 'exists:work_items,id'],
            'items.*.realization' => ['required', 'numeric', 'min:0'],
            'items.*.issues' => ['nullable', 'string'],
            'items.*.solutions' => ['nullable', 'string'],
            'items.*.action_plan' => ['nullable', 'string'],
        ]);

        $action->execute(
            reporter: $employee,
            periodMonth: $validated['period_month'],
            periodYear: $validated['period_year'],
            items: $validated['items'],
        );

        return back()->with('success', 'Laporan kinerja berhasil disimpan.');
    }

    public function destroy(PerformanceReport $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        DB::transaction(function () use ($report) {
            $report->attachments()->each(fn ($a) => $a->delete());
            $report->reviews()->delete();
            $report->delete();
        });

        return back()->with('success', 'Laporan kinerja berhasil dihapus.');
    }
}
