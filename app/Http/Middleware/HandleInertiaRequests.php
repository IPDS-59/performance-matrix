<?php

namespace App\Http\Middleware;

use App\Models\PerformanceIndicator;
use App\Models\PerformancePlan;
use App\Models\Project;
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
                ] : null,
            ],
            'can' => fn () => [
                'view_projects' => $request->user() ? rescue(fn () => $request->user()->can('viewAny', Project::class), false) : false,
                'view_indicators' => $request->user() ? rescue(fn () => $request->user()->can('viewAny', PerformanceIndicator::class), false) : false,
                'view_plans' => $request->user() ? rescue(fn () => $request->user()->can('viewAny', PerformancePlan::class), false) : false,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
