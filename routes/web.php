<?php

use App\Http\Controllers\CheckinController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\OnsiteRegistrationController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\TicketController;
use App\Models\Event;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $events = Event::where('status', 'published')
        ->orderBy('start_datetime')
        ->get();

    return view('welcome', ['events' => $events]);
});

Route::get('/checkin/t/{token}', [CheckinController::class, 'show'])->name('checkin.verify');

Route::get('/ticket/{token}', [TicketController::class, 'show'])->name('ticket.show');
Route::get('/ticket/{token}/download', [TicketController::class, 'download'])->name('ticket.download');

Route::get('/labels/{registration}/print', [LabelController::class, 'printSingle'])->name('labels.print-single');
Route::post('/labels/print', [LabelController::class, 'printBulk'])->name('labels.print');

Route::get('/admin/onsite-register/{event}', [OnsiteRegistrationController::class, 'show'])
    ->name('onsite.register')
    ->middleware('auth');
Route::post('/admin/onsite-register/{event}', [OnsiteRegistrationController::class, 'store'])
    ->name('onsite.register.store')
    ->middleware('auth');

Route::get('/event/{slug}/register', [PublicRegistrationController::class, 'show'])->name('register.show');
Route::post('/event/{slug}/register', [PublicRegistrationController::class, 'store'])
    ->name('register.store')
    ->middleware('throttle:10,1');
Route::get('/event/{slug}/register/success', [PublicRegistrationController::class, 'success'])->name('register.success');
Route::get('/event/{slug}/payment/success', [PublicRegistrationController::class, 'paymentSuccess'])->name('payment.success');
Route::get('/event/{slug}/payment/failure', [PublicRegistrationController::class, 'paymentFailure'])->name('payment.failure');
Route::post('/event/{slug}/payment/retry/{txnId}', [PublicRegistrationController::class, 'paymentRetry'])
    ->name('payment.retry')
    ->middleware('throttle:5,1');
