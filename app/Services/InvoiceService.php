<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceService
{
    public function generateInvoicePdf(Payment $payment): string
    {
        $reg = $payment->registration;
        $event = $payment->event;

        $dompdf = new Dompdf((new Options)->set([
            'isHtml5ParserEnabled' => true,
            'isRemoteEnabled' => true,
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'portrait',
        ]));

        $html = view('invoices.payment', [
            'payment' => $payment,
            'registration' => $reg,
            'event' => $event,
            'amount' => $payment->getAmountRupees(),
            'invoiceNumber' => $payment->invoice_number,
        ])->render();

        $dompdf->loadHtml($html);
        $dompdf->render();

        return $dompdf->output();
    }
}
