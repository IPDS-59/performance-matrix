<?php

namespace App\Http\Controllers;

use App\Kinetik\Contracts\SkpRating;
use App\Kinetik\Exceptions\KipApiException;
use App\Models\Employee;
use App\Models\SkpStatus;
use App\Notifications\KinetikNotification;
use App\Services\Kinetik\SkpEvidence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** "Nilai SKP": the atasan scores each RK and behaviour of a quarter SKP in Kinetik. Off unless `kinetik.kip.atasan_rating`. */
class SkpRatingController extends Controller
{
    public function __construct(private readonly SkpRating $rating, private readonly SkpEvidence $evidence) {}

    public function show(Request $request, SkpStatus $skpStatus): Response|RedirectResponse
    {
        $this->authorizeRating($request, $skpStatus);

        $data = null;
        $error = null;
        try {
            $data = $this->rating->load($request->user(), $skpStatus);
        } catch (RuntimeException|KipApiException $e) {
            $error = $e->getMessage();
        }

        return Inertia::render('Skp/Rate', [
            'skp' => ['id' => $skpStatus->id, 'label' => $skpStatus->label(), 'employee' => $skpStatus->employee->display_name ?? $skpStatus->employee->name],
            'rks' => $data['rks'] ?? [],
            'perilaku' => $data['perilaku'] ?? [],
            'evidence' => $this->evidence->forQuarter($skpStatus),
            'loadError' => $error,
        ]);
    }

    public function saveRk(Request $request, SkpStatus $skpStatus): RedirectResponse
    {
        $this->authorizeRating($request, $skpStatus);
        $v = $request->validate(['id' => 'required|string', 'nilai' => 'required|integer|between:0,100', 'umpanbalik' => 'nullable|string|max:1000']);

        return $this->act(fn () => $this->rating->saveRk($request->user(), $skpStatus, $v['id'], $v['nilai'], $v['umpanbalik'] ?? null), 'Nilai RK disimpan.');
    }

    public function savePerilaku(Request $request, SkpStatus $skpStatus): RedirectResponse
    {
        $this->authorizeRating($request, $skpStatus);
        $v = $request->validate(['id' => 'required|integer', 'nilai' => 'required|integer|between:0,100', 'umpanbalik' => 'nullable|string|max:1000']);

        return $this->act(fn () => $this->rating->savePerilaku($request->user(), $skpStatus, $v['id'], $v['nilai'], $v['umpanbalik'] ?? null), 'Nilai perilaku disimpan.');
    }

    public function finish(Request $request, SkpStatus $skpStatus): RedirectResponse
    {
        $this->authorizeRating($request, $skpStatus);

        try {
            $this->rating->finish($request->user(), $skpStatus);
        } catch (RuntimeException|KipApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        $by = $request->user()->employee->display_name ?? $request->user()->name;
        Employee::find($skpStatus->employee_id)?->user?->notify(new KinetikNotification('skp_dinilai', "{$by} selesai menilai {$skpStatus->label()}.", route('skp.index')));

        return redirect()->route('skp.approvals')->with('success', "{$skpStatus->label()} selesai dinilai.");
    }

    private function authorizeRating(Request $request, SkpStatus $skp): void
    {
        abort_unless(config('kinetik.kip.atasan_rating'), 404);
        abort_unless(SkpApprovalController::canApprove($request->user()), 403);
        abort_unless($skp->kind === 'quarter', 404);
    }

    private function act(callable $do, string $success): RedirectResponse
    {
        try {
            $do();
        } catch (RuntimeException|KipApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
