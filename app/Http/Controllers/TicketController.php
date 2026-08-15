<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Services\TicketService;

class TicketController extends Controller
{
    public function show(string $token)
    {
        $registration = Registration::where('qr_hash', $token)->first();

        if (! $registration) {
            return response()->view('checkin.invalid', [], 404);
        }

        $ticketService = new TicketService;

        return view('tickets.public', [
            'html' => $ticketService->generateHtml($registration),
            'registration' => $registration,
            'event' => $registration->event,
        ]);
    }

    public function download(string $token)
    {
        $registration = Registration::where('qr_hash', $token)->first();

        if (! $registration) {
            abort(404);
        }

        $ticketService = new TicketService;
        $pdf = $ticketService->generatePdf($registration);

        // Inline: the browser's PDF viewer opens it, with its own download button.
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$registration->guest_number.'-ticket.pdf"',
        ]);
    }
}
