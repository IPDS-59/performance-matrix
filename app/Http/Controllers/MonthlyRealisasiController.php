<?php

namespace App\Http\Controllers;

use App\Actions\Kinetik\CompletePlanItemAction;
use App\Models\PlanItem;
use App\Rules\CapaianWithinTarget;
use App\Services\Kinetik\PlanItemAccess;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One page to fill target and capaian of a whole month, mostly last month: the member no longer opens each kegiatan, and
 * what is ready goes to kipApp in the same save, so the month is not filled once in Kinetik and again in kipApp.
 */
class MonthlyRealisasiController extends Controller
{
    public function __construct(private readonly PlanItemAccess $access) {}

    public function index(Request $request): Response
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $month = $this->month($request->query('month'));
        $items = PlanItem::with(['performancePlan:id,description', 'project:id,name'])
            ->where('employee_id', $employee->id)->where('status', '!=', 'cancelled')
            ->whereBetween('date_start', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
            ->orderBy('date_start')->orderBy('id')->get();

        return Inertia::render('Kinetik/MonthlyRealisasi', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $month->locale('id')->translatedFormat('F Y'),
            'items' => $items->map(fn (PlanItem $i) => [
                'id' => $i->id,
                'date' => substr((string) $i->date_start, 0, 10),
                'description' => $i->description,
                'rk' => $i->performancePlan?->description,
                'project' => $i->project?->name,
                'target' => $i->target,
                'target_unit' => $i->target_unit,
                'capaian' => $i->capaian,
                'capaian_note' => $i->capaian_note,
                'evidence_url' => $i->evidence_url,
                'progress' => (int) $i->progress,
                'in_kip' => $i->kip_external_id !== null,
                'sent' => $i->kip_sent_at !== null,
                'lock' => $this->access->lockReason($i),
            ])->values(),
        ]);
    }

    public function store(Request $request, CompletePlanItemAction $complete): RedirectResponse
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Akun tidak terhubung ke data pegawai.');

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:300'],
            'rows.*.id' => ['required', 'integer'],
            'rows.*.target' => ['nullable', 'numeric', 'gt:0'],
            'rows.*.target_unit' => ['nullable', 'string', 'max:100'],
            'rows.*.capaian' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'rows.*.capaian_note' => ['nullable', 'string', 'max:1000'],
            'rows.*.evidence_url' => ['nullable', 'url', 'max:2000'],
            'rows.*.progress' => ['nullable', 'integer', 'between:0,100'],
            'rows.*.to_kip' => ['nullable', 'boolean'],
        ]);

        $items = PlanItem::where('employee_id', $employee->id)->where('status', '!=', 'cancelled')->whereIn('id', collect($data['rows'])->pluck('id'))->get()->keyBy('id');
        // Capaian cannot be more than the target, the one sent with the row or else the one stored.
        foreach ($data['rows'] as $i => $row) {
            $stored = $items->get((int) $row['id'])?->target;
            $rule = new CapaianWithinTarget($stored !== null ? (float) $stored : null);
            Validator::make(['rows' => [$i => $row]], ["rows.{$i}.capaian" => [$rule]])->validate();
        }
        $saved = 0;
        $sent = 0;
        $problems = [];

        foreach ($data['rows'] as $row) {
            $item = $items->get((int) $row['id']);
            if ($item === null) {
                continue;
            }
            $label = mb_strimwidth((string) $item->description, 0, 40, '…');
            $fields = collect($row)->only(['target', 'target_unit', 'capaian', 'capaian_note', 'evidence_url'])->all();
            if (isset($row['progress'])) {
                $fields['progress'] = (int) $row['progress'];
            }

            // Kinetik first: the numbers are never lost when kipApp refuses.
            $item->update($fields);
            $saved++;

            if (! ($row['to_kip'] ?? false)) {
                continue;
            }
            $note = trim((string) ($row['capaian_note'] ?? $item->capaian_note));
            if (mb_strlen($note) < 10) {
                $problems[] = "{$label}: uraian capaian minimal 10 karakter untuk dikirim ke kipApp.";

                continue;
            }
            if ($reason = $this->access->lockReason($item)) {
                $problems[] = "{$label}: {$reason}";

                continue;
            }
            if ($item->kip_external_id === null && ! $this->access->hasStarted($item)) {
                $problems[] = "{$label}: ".PlanItemAccess::NOT_STARTED;

                continue;
            }
            try {
                $complete->execute($item->load(['employee.user', 'performancePlan']), $note, $row['evidence_url'] ?? null, isset($row['capaian']) ? (float) $row['capaian'] : null);
                $sent++;
            } catch (\RuntimeException $e) {
                $problems[] = "{$label}: ".$e->getMessage();
            }
        }

        $message = "{$saved} kegiatan disimpan di Kinetik".($sent ? ", {$sent} ditandai selesai di kipApp" : '').'.';

        return back()->with($problems === [] ? 'success' : 'warning', $problems === [] ? $message : $message.' Belum berhasil: '.implode(' ', array_slice($problems, 0, 5)).(count($problems) > 5 ? ' (dan '.(count($problems) - 5).' lainnya)' : ''));
    }

    private function month(mixed $value): Carbon
    {
        $tz = config('app.schedule_timezone', 'Asia/Makassar');
        if (is_string($value) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01', $tz)->startOfMonth();
        }

        return now($tz)->subMonthNoOverflow()->startOfMonth();
    }
}
