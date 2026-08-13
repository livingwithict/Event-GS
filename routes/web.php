<?php

use App\Http\Controllers\CheckinController;
use App\Http\Controllers\EventSwitcherController;
use App\Http\Controllers\LabelController;
use App\Http\Controllers\OnsiteRegistrationController;
use App\Http\Controllers\PublicRegistrationController;
use App\Http\Controllers\RegistrationQrController;
use App\Http\Controllers\ReportDownloadController;
use App\Http\Controllers\TicketController;
use App\Models\Event;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    // Upcoming first, soonest at the top; anything already over drops below.
    $events = Event::where('status', 'published')
        ->orderByRaw('(start_datetime < now()) asc, start_datetime asc')
        ->get();

    return view('welcome', ['events' => $events]);
});

Route::get('/checkin/t/{token}', [CheckinController::class, 'show'])->name('checkin.verify');

Route::get('/ticket/{token}', [TicketController::class, 'show'])->name('ticket.show');
Route::get('/ticket/{token}/download', [TicketController::class, 'download'])->name('ticket.download');
Route::get('/ticket/{token}/qr-print', [RegistrationQrController::class, 'download'])->name('ticket.qr-print');

Route::middleware('auth')->group(function () {
    Route::get('/labels/{registration}/print', [LabelController::class, 'printSingle'])->name('labels.print-single');
    Route::post('/labels/print', [LabelController::class, 'printBulk'])->name('labels.print');
    // Auto-print: a wrapper page that loads the PDF and fires the print dialog.
    Route::get('/labels/print-now', [LabelController::class, 'printNow'])->name('labels.print-now');
    Route::get('/labels/pdf', [LabelController::class, 'pdf'])->name('labels.pdf');
});

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

Route::post('/event-switcher/switch', [EventSwitcherController::class, 'switch'])
    ->name('event-switcher.switch')
    ->middleware('auth');
Route::get('/event-switcher/events', [EventSwitcherController::class, 'getEvents'])
    ->name('event-switcher.events')
    ->middleware('auth');

Route::get('/reports/{event}/pdf-summary', [ReportDownloadController::class, 'pdfSummary'])
    ->name('reports.pdf-summary')
    ->middleware('auth');
Route::get('/reports/{event}/payments', [ReportDownloadController::class, 'payments'])
    ->name('reports.payments')
    ->middleware('auth');
Route::get('/reports/{event}/scanner-activity', [ReportDownloadController::class, 'scannerActivity'])
    ->name('reports.scanner-activity')
    ->middleware('auth');
Route::get('/reports/{event}/category-summary', [ReportDownloadController::class, 'categorySummary'])
    ->name('reports.category-summary')
    ->middleware('auth');
Route::get('/reports/{event}/card-delivery', [ReportDownloadController::class, 'cardDelivery'])
    ->name('reports.card-delivery')
    ->middleware('auth');
