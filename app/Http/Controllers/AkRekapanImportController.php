<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\CompareAkRekapanAction;
use App\Actions\Kinetik\PreviewAkRekapanAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Admin: load the cumulative AK of the years before kipApp-based counting from the office's "Rekapan SKP" workbook (read in the browser). */
class AkRekapanImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ImportAk', ['defaultBaseDate' => '2025-12-31']);
    }

    public function preview(Request $request, PreviewAkRekapanAction $preview): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:1500'],
            'rows.*.nip' => ['nullable', 'string', 'max:30'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.integrasi' => ['nullable', 'numeric'],
            'rows.*.y2023' => ['nullable', 'numeric'],
            'rows.*.y2024' => ['nullable', 'numeric'],
            'rows.*.y2025' => ['nullable', 'numeric'],
            'rows.*.jumlah' => ['nullable', 'numeric'],
            'rows.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json(['rows' => $preview->execute($data['rows'])]);
    }

    /** Yearly AK of the workbook next to Kinetik's own figure for the same year. */
    public function compare(Request $request, CompareAkRekapanAction $compare): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:1500'],
            'rows.*.nip' => ['nullable', 'string', 'max:30'],
            'rows.*.name' => ['nullable', 'string', 'max:255'],
            'rows.*.y2023' => ['nullable', 'numeric'],
            'rows.*.y2024' => ['nullable', 'numeric'],
            'rows.*.y2025' => ['nullable', 'numeric'],
        ]);

        return response()->json(['rows' => $compare->execute($data['rows'])]);
    }

    public function store(Request $request, PreviewAkRekapanAction $preview): JsonResponse
    {
        $data = $request->validate([
            'base_date' => ['required', 'date', 'before_or_equal:today'],
            'rows' => ['required', 'array', 'min:1', 'max:1500'],
            'rows.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'rows.*.ak' => ['required', 'numeric', 'min:0', 'max:9999'],
        ]);

        return response()->json(['saved' => $preview->apply($data['rows'], $data['base_date'])]);
    }
}
