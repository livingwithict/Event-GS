<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommunicationController;
use App\Http\Controllers\Api\EntryController;
use App\Http\Controllers\Api\EventDashboardController;
use App\Http\Controllers\Api\GuestSearchController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Scanner endpoints (scanner, event_manager, super_admin)
    Route::middleware(['role:scanner,event_manager,super_admin', 'throttle:60,1', 'idempotent'])->group(function () {
        Route::post('/scan', [ScanController::class, 'store']);
        Route::post('/entry', [EntryController::class, 'store']);
        Route::post('/meal', [MealController::class, 'store']);
        Route::get('/guest/search', [GuestSearchController::class, 'index']);
    });

    // Manager+ endpoints
    Route::middleware('role:event_manager,super_admin')->group(function () {
        Route::get('/event/{event}/dashboard', [EventDashboardController::class, 'show']);
        Route::post('/event/{event}/import', [ImportController::class, 'import']);
        Route::post('/event/{event}/send-invites', [CommunicationController::class, 'sendInvites']);
        Route::get('/reports/attendance/{event}', [ReportController::class, 'attendance']);
        Route::get('/reports/noshow/{event}', [ReportController::class, 'noShow']);
        Route::get('/reports/duplicate-scans/{event}', [ReportController::class, 'duplicateScans']);
        Route::get('/reports/communications/{event}', [ReportController::class, 'communications']);
        Route::get('/reports/meal-usage/{event}', [ReportController::class, 'mealUsage']);
        Route::get('/reports/event-summary/{event}', [ReportController::class, 'eventSummary']);
    });
});
