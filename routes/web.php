<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PerformanceApprovalController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectDetailController;
use App\Http\Controllers\ProjectListController;
use App\Http\Controllers\ReportAttachmentController;
use App\Http\Controllers\ReportResubmitController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkItemController;
use App\Http\Controllers\WorkItemDetailController;
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
    Route::resource('teams', TeamController::class)->except(['show']);
    Route::resource('employees', EmployeeController::class)->except(['show']);
    Route::resource('projects', ProjectController::class)->except(['show']);
    Route::post('/projects/{project}/copy', [ProjectController::class, 'copy'])->name('projects.copy');

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
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // Report attachments
    Route::post('/performance/{report}/attachments', [ReportAttachmentController::class, 'store'])->name('report-attachments.store');
    Route::get('/report-attachments/{attachment}/download', [ReportAttachmentController::class, 'download'])->name('report-attachments.download');
    Route::delete('/report-attachments/{attachment}', [ReportAttachmentController::class, 'destroy'])->name('report-attachments.destroy');
    Route::patch('/report-attachments/{attachment}/review', [ReportAttachmentController::class, 'review'])->name('report-attachments.review');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
