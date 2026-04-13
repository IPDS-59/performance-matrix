<?php

namespace App\Providers;

use App\Events\EmployeeLinkedToUser;
use App\Events\PerformanceBatchSubmitted;
use App\Events\PerformanceReportSaved;
use App\Events\ProjectMembersUpdated;
use App\Listeners\AssignStaffRole;
use App\Listeners\LogPerformanceActivity;
use App\Listeners\NotifyTeamLeadOnReportSubmitted;
use App\Listeners\RecalculateTeamProgress;
use App\Listeners\SyncProjectLeaderRole;
use App\Models\PerformanceReport;
use App\Policies\PerformancePolicy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(PerformanceReport::class, PerformancePolicy::class);

        Event::listen(PerformanceReportSaved::class, LogPerformanceActivity::class);
        Event::listen(PerformanceReportSaved::class, RecalculateTeamProgress::class);
        Event::listen(PerformanceBatchSubmitted::class, LogPerformanceActivity::class);
        Event::listen(PerformanceBatchSubmitted::class, RecalculateTeamProgress::class);
        Event::listen(PerformanceBatchSubmitted::class, NotifyTeamLeadOnReportSubmitted::class);
        Event::listen(ProjectMembersUpdated::class, SyncProjectLeaderRole::class);
        Event::listen(EmployeeLinkedToUser::class, AssignStaffRole::class);
    }
}
