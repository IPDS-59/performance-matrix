<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeReportController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkItemController;
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

    // Work items (nested under project for create, standalone for update/delete)
    Route::post('/projects/{project}/work-items', [WorkItemController::class, 'store'])->name('work-items.store');
    Route::put('/work-items/{workItem}', [WorkItemController::class, 'update'])->name('work-items.update');
    Route::delete('/work-items/{workItem}', [WorkItemController::class, 'destroy'])->name('work-items.destroy');

    // Employee mutation (transfer)
    Route::post('/employees/{employee}/mutasi', [EmployeeController::class, 'storeMutation'])->name('employees.mutasi');
    Route::get('/employees/{employee}/mutasi', [EmployeeController::class, 'mutationHistory'])->name('employees.mutasi.history');

    // Reports
    Route::get('/laporan/pegawai', [EmployeeReportController::class, 'index'])->name('laporan.pegawai');

    // Staff performance entry
    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::post('/performance/batch', [PerformanceController::class, 'storeBatch'])->name('performance.batch');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
