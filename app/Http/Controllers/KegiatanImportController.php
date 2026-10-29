<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\ImportKegiatanAction;
use App\Actions\Kinetik\PreviewKegiatanImportAction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Admin: bring the "Kegiatan Mingguan Anggota" Excel template in as planned kegiatan. The file is read in the browser; the server only matches and saves. */
class KegiatanImportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ImportKegiatan', [
            'defaultWeek' => now(config('app.schedule_timezone', 'Asia/Makassar'))->startOfWeek(Carbon::MONDAY)->toDateString(),
            'tahun' => (int) config('kinetik.kip.tahun'),
        ]);
    }

    public function preview(Request $request, PreviewKegiatanImportAction $preview): JsonResponse
    {
        $data = $request->validate([
            'week_start' => ['required', 'date'],
            'rows' => ['required', 'array', 'min:1', 'max:3000'],
            'rows.*.description' => ['required', 'string', 'max:2000'],
            'rows.*.member' => ['required', 'string', 'max:255'],
            'rows.*.project' => ['nullable', 'string', 'max:255'],
            'rows.*.nip' => ['nullable', 'string', 'max:30'],
            'rows.*.sheet' => ['nullable', 'string', 'max:100'],
            'rows.*.date_start' => ['nullable', 'date'],
            'rows.*.date_end' => ['nullable', 'date'],
            'rows.*.time_start' => ['nullable', 'date_format:H:i'],
            'rows.*.time_end' => ['nullable', 'date_format:H:i'],
            'rows.*.target' => ['nullable', 'numeric'],
            'rows.*.realization' => ['nullable', 'numeric'],
            'rows.*.percent' => ['nullable', 'numeric'],
            'rows.*.note' => ['nullable', 'string', 'max:2000'],
            'rows.*.link' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json(['rows' => $preview->execute($data['rows'], Carbon::parse($data['week_start']))]);
    }

    public function store(Request $request, ImportKegiatanAction $import): JsonResponse
    {
        abort_if($request->user()->employee === null, 403, 'Akun tidak terhubung ke data pegawai.');
        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:3000'],
            'rows.*.employee_id' => ['required', 'integer', 'exists:employees,id'],
            'rows.*.performance_plan_id' => ['required', 'integer', 'exists:performance_plans,id'],
            'rows.*.project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'rows.*.description' => ['required', 'string', 'min:10', 'max:1000'],
            'rows.*.date_start' => ['required', 'date'],
            'rows.*.date_end' => ['required', 'date', 'after_or_equal:rows.*.date_start'],
            'rows.*.time_start' => ['nullable', 'date_format:H:i'],
            'rows.*.time_end' => ['nullable', 'date_format:H:i'],
            'rows.*.target' => ['nullable', 'numeric', 'gt:0'],
            'rows.*.capaian' => ['nullable', 'numeric', 'min:0'],
            'rows.*.progress' => ['nullable', 'integer', 'between:0,100'],
            'rows.*.evidence_url' => ['nullable', 'string', 'max:2000'],
            'rows.*.capaian_note' => ['nullable', 'string', 'max:2000'],
        ]);

        return response()->json($import->execute($data['rows'], $request->user()->employee));
    }
}
