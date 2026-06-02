<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;

class ConnectIPSService
{
    public function initiatePayment(Payment $payment): string
    {
        $registration = $payment->registration;
        $event = $payment->event;

        $txnDate = now()->format('d-m-Y');
        $remarks = "Event Registration - {$event->name}";
        $particulars = "Guest: {$registration->name}";

        $token = $this->generateToken([
            'MERCHANTID' => config('connectips.merchant_id'),
            'APPID' => config('connectips.app_id'),
            'APPNAME' => config('connectips.app_name'),
            'TXNID' => $payment->transaction_id,
            'TXNDATE' => $txnDate,
            'TXNCRNCY' => $payment->currency,
            'TXNAMT' => $payment->amount_paisa,
            'REFERENCEID' => $registration->guest_number ?? $payment->transaction_id,
            'REMARKS' => $remarks,
            'PARTICULARS' => $particulars,
        ]);

        $payment->update(['payment_status' => 'initiated']);

        return $this->renderAutoSubmitForm([
            'MERCHANTID' => config('connectips.merchant_id'),
            'APPID' => config('connectips.app_id'),
            'APPNAME' => config('connectips.app_name'),
            'TXNID' => $payment->transaction_id,
            'TXNDATE' => $txnDate,
            'TXNCRNCY' => $payment->currency,
            'TXNAMT' => $payment->amount_paisa,
            'REFERENCEID' => $registration->guest_number ?? $payment->transaction_id,
            'REMARKS' => $remarks,
            'PARTICULARS' => $particulars,
            'TOKEN' => $token,
        ]);
    }

    public function validatePayment(Payment $payment): array
    {
        $token = $this->generateValidationToken([
            'MERCHANTID' => config('connectips.merchant_id'),
            'APPID' => config('connectips.app_id'),
            'REFERENCEID' => $payment->transaction_id,
            'TXNAMT' => $payment->amount_paisa,
        ]);

        $response = Http::withBasicAuth(
            config('connectips.app_id'),
            config('connectips.app_password')
        )->post(config('connectips.base_url').'/connectipswebws/api/creditor/validatetxn', [
            'merchantId' => (int) config('connectips.merchant_id'),
            'appId' => config('connectips.app_id'),
            'referenceId' => $payment->transaction_id,
            'txnAmt' => $payment->amount_paisa,
            'token' => $token,
        ]);

        return $response->json();
    }

    public function generateToken(array $params): string
    {
        $message = collect([
            'MERCHANTID', 'APPID', 'APPNAME', 'TXNID',
            'TXNDATE', 'TXNCRNCY', 'TXNAMT', 'REFERENCEID',
            'REMARKS', 'PARTICULARS',
        ])->map(fn ($key) => "{$key}={$params[$key]}")->implode(', ');

        $message .= ',TOKEN=TOKEN';

        return $this->signWithPrivateKey($message);
    }

    public function generateValidationToken(array $params): string
    {
        $message = "MERCHANTID={$params['MERCHANTID']},APPID={$params['APPID']},REFERENCEID={$params['REFERENCEID']},TXNAMT={$params['TXNAMT']}";

        return $this->signWithPrivateKey($message);
    }

    private function signWithPrivateKey(string $message): string
    {
        $keyPath = config('connectips.private_key_path');
        $passphrase = config('connectips.private_key_passphrase', '');

        if (! $keyPath || ! file_exists($keyPath)) {
            logger()->warning('Connect IPS: Private key not found at '.$keyPath);

            return base64_encode(hash('sha256', $message, true));
        }

        $privateKey = openssl_pkey_get_private(
            file_get_contents($keyPath),
            $passphrase
        );

        if (! $privateKey) {
            logger()->error('Connect IPS: Failed to load private key');

            return base64_encode(hash('sha256', $message, true));
        }

        $digest = hash('sha256', $message, true);

        openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        openssl_free_key($privateKey);

        return base64_encode($signature);
    }

    private function renderAutoSubmitForm(array $fields): string
    {
        $actionUrl = config('connectips.base_url').'/connectipswebgw/loginpage';

        $inputs = collect($fields)->map(fn ($value, $key) => "<input type='hidden' name='{$key}' value='{$value}'>")->implode("\n");

        return "<!DOCTYPE html>
<html>
<head><title>Redirecting to Payment...</title></head>
<body>
<p style='text-align:center;padding:40px;font-family:sans-serif'>Redirecting to Connect IPS for secure payment...</p>
<form id='paymentForm' method='POST' action='{$actionUrl}'>
{$inputs}
</form>
<script>document.getElementById('paymentForm').submit();</script>
</body>
</html>";
    }
}
