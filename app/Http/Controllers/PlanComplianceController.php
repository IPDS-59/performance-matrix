<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesTeams;
use App\Models\Team;
use App\Models\User;
use App\Services\Kinetik\PlanComplianceReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Kepatuhan Rencana": who plans their week. The head and admins see every team, a PJ their own. */
class PlanComplianceController extends Controller
{
    use ResolvesTeams;

    public function index(Request $request, PlanComplianceReport $report): Response
    {
        abort_unless(self::canView($request->user()), 403);

        $teams = $request->user()->hasAnyRole(['admin', 'head'])
            ? Team::orderBy('name')->get()
            : Team::where('leader_id', $request->user()->employee->id)->orderBy('name')->get();

        return Inertia::render('Kinetik/PlanCompliance', $report->build($teams, now(config('app.schedule_timezone', 'Asia/Makassar'))));
    }

    public static function canView(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'head'])
            || ($user->employee !== null && Team::where('leader_id', $user->employee->id)->exists());
    }
}
