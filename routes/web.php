<?php

use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeReportController;
use App\Http\Controllers\FeatureFlagController;
use App\Http\Controllers\FraController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\KipActivityController;
use App\Http\Controllers\KipIntegrationController;
use App\Http\Controllers\KipStructureController;
use App\Http\Controllers\MemberWeekController;
use App\Http\Controllers\MyActivitySyncController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PerformanceApprovalController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PerformanceIndicatorController;
use App\Http\Controllers\PerformancePlanController;
use App\Http\Controllers\PlanComplianceController;
use App\Http\Controllers\PlanItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDetailController;
use App\Http\Controllers\ProjectListController;
use App\Http\Controllers\ReportAttachmentController;
use App\Http\Controllers\ReportResubmitController;
use App\Http\Controllers\SkpApprovalController;
use App\Http\Controllers\SkpController;
use App\Http\Controllers\SkpRatingController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\TeamRecapController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\WeeklyPlanController;
use App\Http\Controllers\WorkItemController;
use App\Http\Controllers\WorkItemDetailController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard — role-aware
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Matrix — head + admin
    Route::get('/matrix', [DashboardController::class, 'matrix'])->name('matrix');

    // Admin CRUD
    Route::resource('teams', TeamController::class)->except(['show', 'create', 'edit']);
    Route::get('/teams/{team}/members', [TeamMemberController::class, 'edit'])->name('teams.members.edit');
    Route::put('/teams/{team}/members', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::resource('employees', EmployeeController::class)->except(['show', 'create']);
    Route::resource('projects', ProjectController::class)->except(['show']);
    Route::get('/kip/struktur/opsi', [KipStructureController::class, 'options'])->name('kip.structure.options');
    Route::post('/kip/rk-ketua', [KipStructureController::class, 'storeRkKetua'])->name('kip.rk-ketua.store');
    Route::post('/projects/{project}/kirim-kipapp', [KipStructureController::class, 'pushProject'])->name('projects.push');
    Route::post('/performance-plans/{performance_plan}/kirim-kipapp', [KipStructureController::class, 'pushPlan'])->name('performance-plans.push');
    Route::post('/projects/{project}/copy', [ProjectController::class, 'copy'])->name('projects.copy');
    Route::resource('performance-indicators', PerformanceIndicatorController::class)->except(['show', 'create', 'edit']);
    Route::resource('performance-plans', PerformancePlanController::class)->except(['show', 'create', 'edit']);

    // Work items (nested under project for create, standalone for update/delete)
    Route::post('/projects/{project}/work-items', [WorkItemController::class, 'store'])->name('work-items.store');
    Route::put('/work-items/{workItem}', [WorkItemController::class, 'update'])->name('work-items.update');
    Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy'])->name('work-items.destroy');

    // Employee mutation (transfer)
    Route::post('/employees/{employee}/mutasi', [EmployeeController::class, 'storeMutation'])->name('employees.mutasi');
    Route::get('/employees/{employee}/mutasi', [EmployeeController::class, 'mutationHistory'])->name('employees.mutasi.history');

    // Employee education history
    Route::post('/employees/{employee}/educations', [EmployeeController::class, 'storeEducation'])->name('employees.educations.store');
    Route::put('/employees/{employee}/educations/{education}', [EmployeeController::class, 'updateEducation'])->name('employees.educations.update');
    Route::delete('/employees/{employee}/educations/{education}', [EmployeeController::class, 'destroyEducation'])->name('employees.educations.destroy');

    // Admin: look at the app as another user (read only)
    Route::get('/admin/lihat-sebagai', [ImpersonationController::class, 'index'])->name('admin.impersonate');
    Route::post('/admin/lihat-sebagai/keluar', [ImpersonationController::class, 'stop'])->name('admin.impersonate.stop');
    Route::post('/admin/lihat-sebagai/{user}', [ImpersonationController::class, 'start'])->name('admin.impersonate.start');

    // Reports
    Route::get('/laporan/pegawai', [EmployeeReportController::class, 'index'])->name('laporan.pegawai');

    // Staff performance entry
    Route::get('/performance', [ProjectListController::class, 'index'])->name('performance.index');
    Route::get('/performance/projects/{project}', [ProjectDetailController::class, 'show'])->name('performance.projects.show');
    Route::get('/performance/work-items/{workItem}', [WorkItemDetailController::class, 'show'])->name('performance.work-items.show');
    Route::post('/performance/batch', [PerformanceController::class, 'storeBatch'])->name('performance.batch');
    Route::delete('/performance/{report}', [PerformanceController::class, 'destroy'])->name('performance.destroy');
    Route::patch('/performance/{report}/resubmit', [ReportResubmitController::class, 'store'])->name('performance.resubmit');

    // Report approval (team leads + head)
    Route::patch('/performance/{report}/approve', [PerformanceApprovalController::class, 'approve'])->name('performance.approve');
    Route::patch('/performance/{report}/reject', [PerformanceApprovalController::class, 'reject'])->name('performance.reject');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifikasi', [NotificationController::class, 'page'])->name('notifications.page');

    // In-app handbook (Buku Pedoman)
    Route::get('/buku-pedoman', GuideController::class)->name('guide');
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Report attachments
    Route::post('/performance/{report}/attachments', [ReportAttachmentController::class, 'store'])->name('report-attachments.store');
    Route::get('/report-attachments/{attachment}/download', [ReportAttachmentController::class, 'download'])->name('report-attachments.download');
    Route::delete('/report-attachments/{attachment}', [ReportAttachmentController::class, 'destroy'])->name('report-attachments.destroy');
    Route::patch('/report-attachments/{attachment}/review', [ReportAttachmentController::class, 'review'])->name('report-attachments.review');

    // kipApp activities — admins see all, others see their own
    Route::get('/kegiatan-kipapp', [KipActivityController::class, 'index'])->name('kip-activities.index');

    // Weekly activity scrapper / recap
    // The old plan page; old links and notifications still land on the new one.
    Route::get('/rencana-minggu', fn (Request $request) => redirect()->route('weekly.index', $request->only('week')))->name('weekly-plan.index');
    Route::get('/struktur-organisasi', [OrganizationController::class, 'index'])->name('organization.index');
    Route::get('/fra', [FraController::class, 'index'])->name('fra.index');
    Route::patch('/fra/{indicator}/triwulan/{quarter}', [FraController::class, 'updateValues'])->name('fra.values');
    Route::patch('/fra/{indicator}/pemilik', [FraController::class, 'updateOwner'])->name('fra.owner');
    Route::get('/kepatuhan-rencana', [PlanComplianceController::class, 'index'])->name('plan-compliance.index');
    Route::post('/rencana-minggu/fokus', [WeeklyPlanController::class, 'storeFocus'])->name('weekly-plan.focus');
    Route::post('/rencana-minggu/item', [PlanItemController::class, 'store'])->name('plan-items.store');
    Route::patch('/rencana-minggu/item/{planItem}', [PlanItemController::class, 'update'])->name('plan-items.update');
    Route::post('/rencana-minggu/item/{planItem}/kirim', [PlanItemController::class, 'push'])->name('plan-items.push');
    Route::post('/rencana-minggu/item/{planItem}/kirim-atasan', [PlanItemController::class, 'send'])->name('plan-items.send');
    Route::post('/rencana-minggu/item/{planItem}/batal-kirim', [PlanItemController::class, 'unsend'])->name('plan-items.unsend');
    Route::post('/rencana-minggu/kirim-atasan', [PlanItemController::class, 'sendMany'])->name('plan-items.send-many');
    Route::post('/rencana-minggu/batal-kirim', [PlanItemController::class, 'unsendMany'])->name('plan-items.unsend-many');
    Route::get('/rencana-minggu/presensi', [PlanItemController::class, 'presensi'])->name('plan-items.presensi');
    Route::patch('/rencana-minggu/item/{planItem}/capaian', [PlanItemController::class, 'result'])->name('plan-items.result');
    Route::post('/rencana-minggu/item/{planItem}/selesai', [PlanItemController::class, 'complete'])->name('plan-items.complete');
    Route::patch('/rencana-minggu/item/{planItem}/evaluasi', [PlanItemController::class, 'evaluate'])->name('plan-items.evaluate');
    Route::delete('/rencana-minggu/item/{planItem}', [PlanItemController::class, 'destroy'])->name('plan-items.destroy');
    Route::get('/rekap-mingguan', [MemberWeekController::class, 'index'])->name('weekly.index');

    // Team recaps (weekly / monthly / quarterly FRA)
    Route::get('/rekap-tim', [TeamRecapController::class, 'weekly'])->name('team-recap.weekly');
    Route::get('/rekap-bulanan', [TeamRecapController::class, 'monthly'])->name('team-recap.monthly');
    Route::get('/rekap-triwulanan', [TeamRecapController::class, 'quarterly'])->name('team-recap.quarterly');
    Route::post('/rekap-tim/lock', [TeamRecapController::class, 'toggleLock'])->name('team-recap.lock');
    Route::get('/rekap-semua-tim', [TeamRecapController::class, 'overview'])->name('team-recap.overview');
    Route::post('/rekap-semua-tim/catatan', [TeamRecapController::class, 'storeLeadershipNote'])->name('team-recap.leadership-note');
    Route::get('/rekap-tim/export', [TeamRecapController::class, 'export'])->name('team-recap.export');
    Route::post('/rekap-tim/evidence', [TeamRecapController::class, 'storeEvidence'])->name('team-recap.evidence.store');
    Route::delete('/rekap-tim/evidence/{evidence}', [TeamRecapController::class, 'destroyEvidence'])->name('team-recap.evidence.destroy');
    Route::post('/rekap-tim/rk', [TeamRecapController::class, 'storeRk'])->name('team-recap.rk.store');
    Route::post('/rekap-tim/rk/buka', [TeamRecapController::class, 'unlockRk'])->name('team-recap.rk.unlock');
    Route::post('/rekap-tim/override', [TeamRecapController::class, 'storeOverride'])->name('team-recap.override.store');
    Route::post('/rekap-tim/ringkasan', [TeamRecapController::class, 'storeSummary'])->name('team-recap.summary');
    Route::post('/rekap-tim/override/confirm', [TeamRecapController::class, 'confirmOverride'])->name('team-recap.override.confirm');
    Route::post('/rekap-tim/override/confirm-bulk', [TeamRecapController::class, 'confirmBulk'])->name('team-recap.override.confirm-bulk');

    // A member syncs their own kegiatan from kipApp
    Route::post('/sinkron-saya', MyActivitySyncController::class)->name('my-sync');

    // Angka Kredit (own, team, admin PAK value)
    Route::get('/skp-saya', [SkpController::class, 'index'])->name('skp.index');
    Route::get('/persetujuan-skp', [SkpApprovalController::class, 'index'])->name('skp.approvals');
    Route::post('/persetujuan-skp/{skpStatus}/tetapkan', [SkpApprovalController::class, 'approve'])->name('skp.approve');
    Route::post('/persetujuan-skp/{skpStatus}/kembalikan', [SkpApprovalController::class, 'sendBack'])->name('skp.sendback');
    Route::get('/persetujuan-skp/{skpStatus}/nilai', [SkpRatingController::class, 'show'])->name('skp.rate');
    Route::put('/persetujuan-skp/{skpStatus}/nilai/rk', [SkpRatingController::class, 'saveRk'])->name('skp.rate.rk');
    Route::put('/persetujuan-skp/{skpStatus}/nilai/perilaku', [SkpRatingController::class, 'savePerilaku'])->name('skp.rate.perilaku');
    Route::post('/persetujuan-skp/{skpStatus}/nilai/selesai', [SkpRatingController::class, 'finish'])->name('skp.rate.finish');
    Route::post('/skp-saya/triwulan/{quarter}', [SkpController::class, 'createQuarter'])->name('skp.quarter.create');
    Route::put('/skp-saya/triwulan/{quarter}/rk', [SkpController::class, 'addRks'])->name('skp.quarter.rks');
    Route::post('/skp-saya/iki', [SkpController::class, 'addIki'])->name('skp.iki.store');
    Route::delete('/skp-saya/iki', [SkpController::class, 'deleteIki'])->name('skp.iki.destroy');
    Route::post('/skp-saya/kirim', [SkpController::class, 'submit'])->name('skp.submit');
    Route::post('/skp-saya/batal-kirim', [SkpController::class, 'cancel'])->name('skp.cancel');
    Route::get('/angka-kredit', [CreditController::class, 'mine'])->name('credit.mine');
    Route::get('/angka-kredit/tim', [CreditController::class, 'team'])->name('credit.team');
    Route::put('/angka-kredit/{employee}/awal', [CreditController::class, 'updateBase'])->name('credit.base');
    Route::put('/angka-kredit/{employee}/tw/{quarter}', [CreditController::class, 'updateQuarter'])->name('credit.quarter');
    Route::delete('/angka-kredit/{employee}/tw/{quarter}', [CreditController::class, 'destroyQuarter'])->name('credit.quarter.destroy');

    // kipApp integration (admin: store token + centralized sync)
    Route::middleware('can:manage-kip-integration')->group(function () {
        Route::get('/integrasi-kipapp', [KipIntegrationController::class, 'index'])->name('kip-integration.index');
        Route::post('/integrasi-kipapp/token', [KipIntegrationController::class, 'storeToken'])->name('kip-integration.token');
        Route::post('/integrasi-kipapp/sync', [KipIntegrationController::class, 'syncAll'])->name('kip-integration.sync');
        Route::post('/integrasi-kipapp/sync-structure', [KipIntegrationController::class, 'syncStructure'])->name('kip-integration.sync-structure');
        Route::post('/integrasi-kipapp/sync-careers', [KipIntegrationController::class, 'syncCareers'])->name('kip-integration.sync-careers');
    });

    Route::middleware('can:manage-kip-integration')->group(function () {
        Route::get('/admin/fitur', [FeatureFlagController::class, 'index'])->name('admin.features');
        Route::put('/admin/fitur/{key}', [FeatureFlagController::class, 'update'])->name('admin.features.update');
        Route::delete('/admin/fitur/{key}', [FeatureFlagController::class, 'reset'])->name('admin.features.reset');
    });

    Route::post('/tur/{key}', [TourController::class, 'complete'])->name('tour.complete');
    Route::delete('/tur', [TourController::class, 'reset'])->name('tour.reset');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/nip', [ProfileController::class, 'updateNip'])->name('profile.nip.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
