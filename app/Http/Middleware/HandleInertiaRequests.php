<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\PlanComplianceController;
use App\Http\Controllers\SkpApprovalController;
use App\Models\PerformanceIndicator;
use App\Models\PerformancePlan;
use App\Models\Project;
use App\Models\TourCompletion;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'position' => $request->user()->employee?->position,
                ] : null,
                'has_employee' => $request->user() ? $request->user()->employee !== null : false,
            ],
            'impersonating' => $request->session()->has(ImpersonationController::SESSION_KEY) ? [
                'name' => $request->user()?->employee?->display_name ?? $request->user()?->name,
                'role' => $request->user()?->role,
            ] : null,
            'can' => fn () => [
                'view_projects' => $request->user() ? rescue(fn () => $request->user()->can('viewAny', Project::class), false) : false,
                'view_indicators' => $request->user() ? rescue(fn () => $request->user()->can('viewAny', PerformanceIndicator::class), false) : false,
                'view_plans' => $request->user() ? rescue(fn () => $request->user()->can('viewAny', PerformancePlan::class), false) : false,
                'view_plan_compliance' => rescue(fn () => PlanComplianceController::canView($request->user()), false),
                'write_kip_structure' => (bool) config('kinetik.kip.structure_write'),
                'approve_skp' => rescue(fn () => SkpApprovalController::canApprove($request->user()), false),
                'view_team_credits' => $request->user() ? rescue(fn () => CreditController::visibleTeams($request)->isNotEmpty(), false) : false,
            ],
            'tours_done' => fn () => $request->user() ? rescue(fn () => TourCompletion::where('user_id', $request->user()->id)->pluck('tour_key')->all(), [], false) : [],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
