<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeRk;
use App\Models\PlanItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The member's own record of kegiatan and RK, for keeping after kipApp is retired and for loading into e-Kinerja.
 * A member exports their own; an admin can export anyone's.
 */
class ArchiveController extends Controller
{
    public function index(Request $request): Response
    {
        $admin = $request->user()->hasRole('admin');

        return Inertia::render('Kinetik/Archive', [
            'isAdmin' => $admin,
            'ownEmployeeId' => $request->user()->employee?->id,
            'employees' => $admin ? Employee::where('is_active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($e) => ['value' => $e->id, 'label' => $e->name])->all() : [],
            'years' => range((int) config('kinetik.kip.tahun'), 2024),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $data = $request->validate(['employee_id' => ['required', 'integer', 'exists:employees,id'], 'year' => ['required', 'integer', 'min:2020', 'max:2100']]);
        $employee = Employee::findOrFail($data['employee_id']);
        abort_unless($request->user()->hasRole('admin') || $request->user()->employee?->id === $employee->id, 403);

        $items = PlanItem::with(['performancePlan:id,description,leader_rk,project_id', 'performancePlan.project:id,name', 'performancePlan.team:id,name', 'project:id,name'])
            ->where('employee_id', $employee->id)->where('status', '!=', 'cancelled')
            ->whereYear('date_start', $data['year'])->orderBy('date_start')->get();

        return response()->json([
            'employee' => ['name' => $employee->display_name ?? $employee->name, 'nip' => $employee->nip],
            'kegiatan' => $items->map(fn (PlanItem $i) => [
                'tim' => $i->performancePlan?->team?->name ?? $i->performancePlan?->project?->team?->name,
                'projek' => $i->project?->name ?? $i->performancePlan?->project?->name,
                'rk' => $i->performancePlan?->description,
                'rk_atasan' => $i->performancePlan?->leader_rk,
                'kegiatan' => $i->description,
                'mulai' => substr((string) $i->date_start, 0, 10),
                'selesai' => substr((string) $i->date_end, 0, 10),
                'target' => $i->target,
                'satuan' => $i->target_unit,
                'progres' => $i->progress,
                'uraian_capaian' => $i->capaian_note,
                'bukti' => $i->evidence_url,
                'id_kipapp' => $i->kip_external_id,
            ])->values(),
            'rk' => EmployeeRk::where('employee_id', $employee->id)->where('year', $data['year'])->orderBy('name')->get(['name', 'leader_rk'])
                ->map(fn ($r) => ['rk' => $r->name, 'rk_atasan' => $r->leader_rk])->values(),
        ]);
    }
}
