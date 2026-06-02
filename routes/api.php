<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommunicationController;
use App\Http\Controllers\Api\EntryController;
use App\Http\Controllers\Api\EventDashboardController;
use App\Http\Controllers\Api\EventInfoController;
use App\Http\Controllers\Api\GuestSearchController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ScanActionController;
use App\Http\Controllers\Api\ScanController;
use App\Http\Controllers\ImportController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Scanner endpoints (scanner, event_manager, super_admin, registration_staff)
    Route::middleware(['role:scanner,event_manager,super_admin,registration_staff', 'throttle:60,1', 'idempotent'])->group(function () {
        Route::post('/scan', [ScanController::class, 'store']);
        Route::post('/entry', [EntryController::class, 'store']);
        Route::post('/meal', [MealController::class, 'store']);
        Route::post('/scan-action', [ScanActionController::class, 'store']);
        Route::get('/guest/search', [GuestSearchController::class, 'index']);
        Route::get('/event/{eventId}/actions', [ScanActionController::class, 'index']);
        Route::get('/event/{eventId}/info', [EventInfoController::class, 'show']);
    });

    // Manager+ endpoints (event_manager, super_admin, registration_staff, finance)
    Route::middleware('role:event_manager,super_admin,registration_staff,finance')->group(function () {
        Route::get('/event/{event}/dashboard', [EventDashboardController::class, 'show']);
        Route::get('/reports/attendance/{event}', [ReportController::class, 'attendance']);
        Route::get('/reports/noshow/{event}', [ReportController::class, 'noShow']);
        Route::get('/reports/duplicate-scans/{event}', [ReportController::class, 'duplicateScans']);
        Route::get('/reports/communications/{event}', [ReportController::class, 'communications']);
        Route::get('/reports/meal-usage/{event}', [ReportController::class, 'mealUsage']);
        Route::get('/reports/event-summary/{event}', [ReportController::class, 'eventSummary']);
        Route::get('/reports/event-summary-pdf/{event}', [ReportController::class, 'eventSummaryPdf']);
        Route::get('/reports/payments/{event}', [ReportController::class, 'payments']);
        Route::get('/reports/scanner-activity/{event}', [ReportController::class, 'scannerActivity']);
        Route::get('/reports/category-summary/{event}', [ReportController::class, 'categorySummary']);
        Route::get('/reports/card-delivery/{event}', [ReportController::class, 'cardDelivery']);
    });

    // Manager-only endpoints (import, send invites)
    Route::middleware('role:event_manager,super_admin')->group(function () {
        Route::post('/event/{event}/import', [ImportController::class, 'import']);
        Route::post('/event/{event}/send-invites', [CommunicationController::class, 'sendInvites']);
    });
});
