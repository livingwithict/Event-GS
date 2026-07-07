<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConnectIPSService
{
    public const TXN_ID_MAX = 20;

    public const REFERENCE_ID_MAX = 20;

    public const REMARKS_MAX = 50;

    public const PARTICULARS_MAX = 100;

    public const TXN_TOKEN_FIELDS = [
        'MERCHANTID', 'APPID', 'APPNAME', 'TXNID',
        'TXNDATE', 'TXNCRNCY', 'TXNAMT', 'REFERENCEID',
        'REMARKS', 'PARTICULARS',
    ];

    public const TXN_AMT_MAX = 20;

    public const TOKEN_MAX = 512;

    public function initiatePayment(Payment $payment): string
    {
        $registration = $payment->registration;
        $event = $payment->event;

        $txnDate = now()->format('d-m-Y');
        $remarks = $this->clip("Event Registration - {$event->name}", self::REMARKS_MAX);
        $particulars = $this->clip("Guest: {$registration->name}", self::PARTICULARS_MAX);
        $referenceId = $this->clip(
            $registration->guest_number ?? $payment->transaction_id,
            self::REFERENCE_ID_MAX
        );

        $txnId = $this->clip($payment->transaction_id, self::TXN_ID_MAX);

        $params = [
            'MERCHANTID' => (string) config('connectips.merchant_id'),
            'APPID' => (string) config('connectips.app_id'),
            'APPNAME' => (string) config('connectips.app_name'),
            'TXNID' => $txnId,
            'TXNDATE' => $txnDate,
            'TXNCRNCY' => (string) $payment->currency,
            'TXNAMT' => (int) $payment->amount_paisa,
            'REFERENCEID' => $referenceId,
            'REMARKS' => $remarks,
            'PARTICULARS' => $particulars,
        ];

        $token = $this->generateToken($params);

        Log::info('ConnectIPS: payment initiated', [
            'payment_id' => $payment->id,
            'transaction_id' => $txnId,
            'amount_paisa' => $payment->amount_paisa,
            'endpoint' => config('connectips.base_url').'/connectipswebgw/loginpage',
        ]);

        $payment->update(['payment_status' => 'initiated']);

        return $this->renderAutoSubmitForm($params + ['TOKEN' => $token]);
    }

    public function validatePayment(Payment $payment): array
    {
        $referenceId = $this->clip($payment->transaction_id, self::REFERENCE_ID_MAX);

        $params = [
            'MERCHANTID' => (string) config('connectips.merchant_id'),
            'APPID' => (string) config('connectips.app_id'),
            'REFERENCEID' => $referenceId,
            'TXNAMT' => (int) $payment->amount_paisa,
        ];

        $token = $this->generateValidationToken($params);

        $endpoint = config('connectips.base_url').'/connectipswebws/api/creditor/validatetxn';

        Log::info('ConnectIPS: validateTxn request', [
            'payment_id' => $payment->id,
            'reference_id' => $referenceId,
            'txn_amt' => $payment->amount_paisa,
            'endpoint' => $endpoint,
        ]);

        $response = $this->apiRequest()->post($endpoint, [
            'merchantId' => (int) config('connectips.merchant_id'),
            'appId' => (string) config('connectips.app_id'),
            'referenceId' => $referenceId,
            'txnAmt' => (int) $payment->amount_paisa,
            'token' => $token,
        ]);

        $body = $response->json() ?? [];

        Log::info('ConnectIPS: validateTxn response', [
            'payment_id' => $payment->id,
            'http_status' => $response->status(),
            'status' => $body['status'] ?? null,
            'status_desc' => $body['statusDesc'] ?? null,
        ]);

        return $body;
    }

    public function interpretValidationResult(Payment $payment, array $response): array
    {
        $status = strtoupper((string) ($response['status'] ?? ''));
        $statusDesc = (string) ($response['statusDesc'] ?? '');
        $responseAmt = isset($response['txnAmt']) ? (int) $response['txnAmt'] : null;
        $amountMismatch = $responseAmt !== null && $responseAmt !== (int) $payment->amount_paisa;

        if ($statusMismatch = $amountMismatch) {
            Log::warning('ConnectIPS: amount mismatch on validateTxn', [
                'payment_id' => $payment->id,
                'expected' => $payment->amount_paisa,
                'gateway' => $responseAmt,
            ]);
        }

        return match (true) {
            $status === 'SUCCESS' => [
                'outcome' => 'success',
                'raw_status' => $status,
                'status_desc' => $statusDesc,
                'amount_mismatch' => $amountMismatch,
                'gateway_txn_id' => $response['txnId'] ?? $payment->transaction_id,
            ],

            $status === 'FAILED' => [
                'outcome' => 'failed',
                'raw_status' => $status,
                'status_desc' => $statusDesc,
                'amount_mismatch' => $amountMismatch,
            ],

            $status === 'ERROR' && stripos($statusDesc, 'NOT FOUND') !== false => [
                'outcome' => 'failed',
                'raw_status' => $status,
                'status_desc' => $statusDesc,
                'amount_mismatch' => $amountMismatch,
            ],

            $status === 'ERROR' && stripos($statusDesc, 'INCOMPLETE') !== false => [
                'outcome' => 'pending',
                'raw_status' => $status,
                'status_desc' => $statusDesc,
                'amount_mismatch' => $amountMismatch,
            ],

            default => [
                'outcome' => 'failed',
                'raw_status' => $status ?: 'UNKNOWN',
                'status_desc' => $statusDesc ?: 'Unknown response from gateway',
                'amount_mismatch' => $amountMismatch,
            ],
        };
    }

    public function getTransactionDetail(Payment $payment): array
    {
        $referenceId = $this->clip($payment->transaction_id, self::REFERENCE_ID_MAX);

        $params = [
            'MERCHANTID' => (string) config('connectips.merchant_id'),
            'APPID' => (string) config('connectips.app_id'),
            'REFERENCEID' => $referenceId,
            'TXNAMT' => (int) $payment->amount_paisa,
        ];

        $token = $this->generateValidationToken($params);

        $endpoint = config('connectips.base_url').'/connectipswebws/api/creditor/gettxndetail';

        Log::info('ConnectIPS: getTxnDetail request', [
            'payment_id' => $payment->id,
            'reference_id' => $referenceId,
            'txn_amt' => $payment->amount_paisa,
            'endpoint' => $endpoint,
        ]);

        $response = $this->apiRequest()->post($endpoint, [
            'merchantId' => (int) config('connectips.merchant_id'),
            'appId' => (string) config('connectips.app_id'),
            'referenceId' => $referenceId,
            'txnAmt' => (int) $payment->amount_paisa,
            'token' => $token,
        ]);

        $body = $response->json() ?? [];

        Log::info('ConnectIPS: getTxnDetail response', [
            'payment_id' => $payment->id,
            'http_status' => $response->status(),
            'status' => $body['status'] ?? null,
            'status_desc' => $body['statusDesc'] ?? null,
            'txn_id' => $body['txnId'] ?? null,
            'batch_id' => $body['batchId'] ?? null,
        ]);

        return $body;
    }

    private function apiRequest(): PendingRequest
    {
        $request = Http::withBasicAuth(
            (string) config('connectips.app_id'),
            (string) config('connectips.app_password')
        );

        $clientCert = (string) config('connectips.client_cert_path');
        $privateKey = (string) config('connectips.private_key_path');
        $passphrase = (string) config('connectips.private_key_passphrase');

        $hasCert = $clientCert && file_exists($clientCert);
        $hasKey = $privateKey && file_exists($privateKey);

        if ($hasCert && $hasKey) {
            $request = $request->withOptions([
                'cert' => $clientCert,
                'ssl_key' => $passphrase ? [$privateKey, $passphrase] : $privateKey,
            ]);
        }

        if (! config('connectips.verify_ssl', true)) {
            $request = $request->withOptions(['verify' => false]);
        }

        return $request;
    }

    public function generateToken(array $params): string
    {
        $message = collect(self::TXN_TOKEN_FIELDS)
            ->map(fn ($key) => "{$key}={$params[$key]}")
            ->implode(',');

        $message .= ',TOKEN=TOKEN';

        return $this->signWithPrivateKey($message);
    }

    public function generateValidationToken(array $params): string
    {
        $message = "MERCHANTID={$params['MERCHANTID']},APPID={$params['APPID']},REFERENCEID={$params['REFERENCEID']},TXNAMT={$params['TXNAMT']}";

        return $this->signWithPrivateKey($message);
    }

    public function clip(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }

        return substr($value, 0, $max);
    }

    private function signWithPrivateKey(string $message): string
    {
        $keyPath = (string) config('connectips.private_key_path');
        $passphrase = (string) config('connectips.private_key_passphrase', '');
        $format = (string) config('connectips.private_key_format', 'pem');

        if (empty($keyPath) || ! file_exists($keyPath)) {
            throw new \RuntimeException(
                'ConnectIPS: private key not found at '.$keyPath
                .'. Set CONNECTIPS_PRIVATE_KEY_PATH to a readable key file.'
            );
        }

        $privateKey = $this->loadPrivateKey($keyPath, $passphrase, $format);

        if (! $privateKey) {
            throw new \RuntimeException(
                'ConnectIPS: failed to load private key at '.$keyPath
                .'. Check the file format and passphrase.'
            );
        }

        $digest = hash('sha256', $message, true);

        $signature = '';
        $ok = openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        openssl_free_key($privateKey);

        if (! $ok) {
            throw new \RuntimeException('ConnectIPS: openssl_sign failed: '.openssl_error_string());
        }

        return base64_encode($signature);
    }

    private function loadPrivateKey(string $keyPath, string $passphrase, string $format): \OpenSSLAsymmetricKey|false
    {
        if (strtolower($format) === 'pfx' || strtolower($format) === 'p12') {
            $contents = file_get_contents($keyPath);

            if ($contents === false) {
                return false;
            }

            $certs = [];

            if (! openssl_pkcs12_read($contents, $certs, $passphrase)) {
                Log::error('ConnectIPS: openssl_pkcs12_read failed: '.openssl_error_string());

                return false;
            }

            return openssl_pkey_get_private($certs['pkey'], '');
        }

        return openssl_pkey_get_private(file_get_contents($keyPath), $passphrase);
    }

    private function renderAutoSubmitForm(array $fields): string
    {
        $actionUrl = config('connectips.base_url').'/connectipswebgw/loginpage';

        $inputs = collect($fields)
            ->map(fn ($value, $key) => "<input type='hidden' name='{$key}' value='".e((string) $value)."'>")
            ->implode("\n");

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
