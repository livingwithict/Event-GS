<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Scanner endpoints (scanner, event_manager, super_admin)
    Route::middleware(['role:scanner,event_manager,super_admin', 'throttle:60,1', 'idempotent'])->group(function () {
        Route::post('/scan', [App\Http\Controllers\Api\ScanController::class, 'store']);
        Route::post('/entry', [App\Http\Controllers\Api\EntryController::class, 'store']);
        Route::post('/meal', [App\Http\Controllers\Api\MealController::class, 'store']);
        Route::get('/guest/search', [App\Http\Controllers\Api\GuestSearchController::class, 'index']);
    });

    // Manager+ endpoints
    Route::middleware('role:event_manager,super_admin')->group(function () {
        Route::get('/event/{event}/dashboard', [App\Http\Controllers\Api\EventDashboardController::class, 'show']);
        Route::post('/event/{event}/import', [App\Http\Controllers\ImportController::class, 'import']);
        Route::post('/event/{event}/send-invites', [App\Http\Controllers\Api\CommunicationController::class, 'sendInvites']);
        Route::get('/reports/attendance/{event}', [App\Http\Controllers\Api\ReportController::class, 'attendance']);
        Route::get('/reports/noshow/{event}', [App\Http\Controllers\Api\ReportController::class, 'noShow']);
        Route::get('/reports/duplicate-scans/{event}', [App\Http\Controllers\Api\ReportController::class, 'duplicateScans']);
    });
});
