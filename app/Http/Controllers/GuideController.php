<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    /**
     * The in-app user guide. It opens on the tab that matches the viewer.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = $user->employee;

        $role = match (true) {
            $user->hasRole('admin') => 'admin',
            $user->hasRole('head') => 'pimpinan',
            $employee !== null && (
                Team::where('leader_id', $employee->id)->exists()
                || $employee->teams()->wherePivot('role', 'leader')->exists()
            ) => 'pj',
            default => 'anggota',
        };

        return Inertia::render('Guide/Index', ['defaultRole' => $role]);
    }
}
