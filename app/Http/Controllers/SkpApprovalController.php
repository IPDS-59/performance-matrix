<?php

namespace App\Http\Controllers;

use App\Kinetik\Contracts\SkpApproval;
use App\Kinetik\Exceptions\KipApiException;
use App\Models\Employee;
use App\Models\SkpStatus;
use App\Models\User;
use App\Notifications\KinetikNotification;
use App\Services\Kinetik\SkpEvidence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * "Persetujuan SKP": the atasan sees the SKP that wait for them and sets (yearly) or sends back. The work is
 * done with the atasan's own kipApp token. Rating a quarter SKP is in kipApp unless `kinetik.kip.atasan_rating` turns on the Nilai SKP page.
 */
class SkpApprovalController extends Controller
{
    public function __construct(private readonly SkpApproval $approval, private readonly SkpEvidence $evidence) {}

    /** The menu and the page are for the head, an admin, and anyone who is the atasan of a stored SKP. */
    public static function canApprove(?User $user): bool
    {
        if ($user === null || $user->employee === null) {
            return false;
        }
        if ($user->hasAnyRole(['head', 'admin'])) {
            return true;
        }

        return $user->employee->kip_pegawai_id !== null
            && SkpStatus::where('atasan_pegawai_id', (string) $user->employee->kip_pegawai_id)->exists();
    }

    public function index(Request $request): Response
    {
        abort_unless(self::canApprove($request->user()), 403);

        $mine = null;
        $error = null;
        try {
            $mine = $this->approval->pegawaiId($request->user());
        } catch (RuntimeException|KipApiException $e) {
            $error = $e->getMessage();
        }

        $waiting = SkpStatus::query()->with('employee:id,name,display_name')
            ->where(fn ($q) => $q->where(fn ($y) => $y->where('kind', 'yearly')->where('status_id', SkpStatus::YEARLY_WAITING))
                ->orWhere(fn ($t) => $t->where('kind', 'quarter')->where('status_id', SkpStatus::QUARTER_WAITING)))
            ->when(! $request->user()->hasRole('admin'), fn ($q) => $q->where('atasan_pegawai_id', $mine ?? '-'))
            ->orderBy('status_changed_at')->get();

        return Inertia::render('Skp/Approvals', [
            'rows' => $waiting->map(fn (SkpStatus $s) => [
                'id' => $s->id,
                'employee' => $s->employee->display_name ?? $s->employee->name,
                'label' => $s->label(),
                'kind' => $s->kind,
                'status' => $s->status_text,
                'jml_rk' => $s->jml_rk,
                'atasan' => $s->atasan_name,
                'since' => $s->status_changed_at?->toDateString(),
                'mine' => $mine !== null && $s->atasan_pegawai_id === $mine,
                'evidence' => $this->evidence->forQuarter($s),
            ])->values(),
            'loadError' => $error,
            'canActAsAtasan' => $mine !== null,
            'ratingEnabled' => (bool) config('kinetik.kip.atasan_rating'),
        ]);
    }

    public function approve(Request $request, SkpStatus $skpStatus): RedirectResponse
    {
        return $this->act($request, $skpStatus, fn () => $this->approval->approve($request->user(), $skpStatus),
            "{$skpStatus->label()} ditetapkan.", 'menetapkan');
    }

    public function sendBack(Request $request, SkpStatus $skpStatus): RedirectResponse
    {
        return $this->act($request, $skpStatus, fn () => $this->approval->sendBack($request->user(), $skpStatus),
            "{$skpStatus->label()} dikembalikan ke pegawai.", 'mengembalikan');
    }

    private function act(Request $request, SkpStatus $skp, callable $do, string $success, string $verb): RedirectResponse
    {
        abort_unless(self::canApprove($request->user()), 403);

        try {
            $do();
        } catch (RuntimeException|KipApiException $e) {
            return back()->with('error', $e->getMessage());
        }

        $member = Employee::find($skp->employee_id)?->user;
        $by = $request->user()->employee->display_name ?? $request->user()->name;
        $member?->notify(new KinetikNotification('skp_'.$verb, "{$by} {$verb} {$skp->label()}.", route('skp.index')));

        return back()->with('success', $success);
    }
}
