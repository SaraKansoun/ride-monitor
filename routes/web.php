<?php

use App\Http\Controllers\Admin\AssignmentController;
use App\Http\Controllers\Admin\DriverController as AdminDriverController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\VehicleController as AdminVehicleController;
use App\Http\Controllers\AIAnalysisController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverScoreController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\IncidentMediaController;
use App\Http\Controllers\IncidentReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('active_user')->group(function (): void {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/admin', [DashboardController::class, 'admin'])->middleware('role:admin')->name('dashboard.admin');
        Route::get('dashboard/monitor', [DashboardController::class, 'monitor'])->middleware('role:monitor')->name('dashboard.monitor');
        Route::get('dashboard/driver', [DashboardController::class, 'driver'])->middleware('role:driver')->name('dashboard.driver');
        Route::get('driver-performance', [DriverScoreController::class, 'performance'])->middleware('permission:view own safety score')->name('driver-performance.show');

        Route::resource('incidents', IncidentController::class)->only(['index', 'create', 'store', 'show']);
        Route::patch('incidents/{incident}/deactivate', [IncidentController::class, 'deactivate'])->name('incidents.deactivate');
        Route::patch('incidents/{incident}/reactivate', [IncidentController::class, 'reactivate'])->name('incidents.reactivate');
        Route::get('incident-media/{incidentMedia}', [IncidentMediaController::class, 'show'])->name('incident-media.show');

        Route::get('ai-analyses', [AIAnalysisController::class, 'index'])->name('ai-analyses.index');
        Route::patch('ai-analyses/{aiAnalysis}/deactivate', [AIAnalysisController::class, 'deactivate'])->name('ai-analyses.deactivate');
        Route::patch('ai-analyses/{aiAnalysis}/reactivate', [AIAnalysisController::class, 'reactivate'])->name('ai-analyses.reactivate');

        Route::get('safety-scores', [DriverScoreController::class, 'index'])->name('safety-scores.index');
        Route::patch('driver-scores/{driverScore}/deactivate', [DriverScoreController::class, 'deactivate'])->name('driver-scores.deactivate');
        Route::patch('driver-scores/{driverScore}/reactivate', [DriverScoreController::class, 'reactivate'])->name('driver-scores.reactivate');

        Route::patch('incident-media/{incidentMedia}/deactivate', [IncidentMediaController::class, 'deactivate'])->name('incident-media.deactivate');
        Route::patch('incident-media/{incidentMedia}/reactivate', [IncidentMediaController::class, 'reactivate'])->name('incident-media.reactivate');

        Route::get('incident-reviews', [IncidentReviewController::class, 'index'])->name('incident-reviews.index');
        Route::patch('incidents/{incident}/start-review', [IncidentReviewController::class, 'start'])->name('incidents.review.start');
        Route::post('incidents/{incident}/reviews', [IncidentReviewController::class, 'store'])->name('incidents.reviews.store');
        Route::patch('incident-reviews/{incidentReview}/deactivate', [IncidentReviewController::class, 'deactivate'])->name('incident-reviews.deactivate');
        Route::patch('incident-reviews/{incidentReview}/reactivate', [IncidentReviewController::class, 'reactivate'])->name('incident-reviews.reactivate');

        Route::prefix('admin')->name('admin.')->group(function (): void {
            Route::redirect('/', '/admin/users')->name('index');

            Route::resource('users', AdminUserController::class)->except('destroy');
            Route::patch('users/{user}/deactivate', [AdminUserController::class, 'deactivate'])->name('users.deactivate');
            Route::patch('users/{user}/reactivate', [AdminUserController::class, 'reactivate'])->name('users.reactivate');

            Route::get('drivers/users/{user}/complete', [AdminDriverController::class, 'createForUser'])->name('drivers.complete');
            Route::post('drivers/users/{user}/complete', [AdminDriverController::class, 'storeForUser'])->name('drivers.complete.store');
            Route::resource('drivers', AdminDriverController::class)->except('destroy');
            Route::patch('drivers/{driver}/deactivate', [AdminDriverController::class, 'deactivate'])->name('drivers.deactivate');
            Route::patch('drivers/{driver}/reactivate', [AdminDriverController::class, 'reactivate'])->name('drivers.reactivate');

            Route::resource('vehicles', AdminVehicleController::class)->except('destroy');
            Route::patch('vehicles/{vehicle}/deactivate', [AdminVehicleController::class, 'deactivate'])->name('vehicles.deactivate');
            Route::patch('vehicles/{vehicle}/reactivate', [AdminVehicleController::class, 'reactivate'])->name('vehicles.reactivate');

            Route::get('assignments', [AssignmentController::class, 'index'])->name('assignments.index');
            Route::get('assignments/create', [AssignmentController::class, 'create'])->name('assignments.create');
            Route::post('assignments', [AssignmentController::class, 'store'])->name('assignments.store');
            Route::patch('assignments/{assignment}/unassign', [AssignmentController::class, 'unassign'])->name('assignments.unassign');
        });
    });
});
