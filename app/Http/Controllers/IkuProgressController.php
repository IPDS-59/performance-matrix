<?php

namespace App\Http\Controllers;

use App\Services\Kinetik\IkuProgressReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "Progres per IKU": how far the planned work under each IKU (and of each team) is. Not the FRA realisation. */
class IkuProgressController extends Controller
{
    public function index(Request $request, IkuProgressReport $report): Response
    {
        abort_unless(PlanComplianceController::canView($request->user()), 403);

        $year = (int) $request->query('year', now()->year);

        return Inertia::render('Kinetik/IkuProgress', $report->build($year));
    }
}
