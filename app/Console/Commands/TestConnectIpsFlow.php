<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Payment\ConnectIPSService;
use App\Services\Payment\PaymentRedirector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestConnectIpsFlow extends Command
{
    protected $signature = 'connectips:test-flow {--live : Hit real NCHL UAT instead of mock}';

    protected $description = 'End-to-end test of the ConnectIPS payment flow (auto-submit form -> gateway -> validate -> reconcile)';

    public function handle(): int
    {
        $live = (bool) $this->option('live');

        $this->info("\n=== ConnectIPS ".($live ? 'LIVE UAT' : 'MOCKED')." End-to-End Test ===\n");

        $event = Event::factory()->create([
            'name' => 'ICT Nepal Annual Conference 2026',
            'slug' => 'connectips-test-'.now()->timestamp,
            'settings' => ['enable_payment' => true, 'tax_rate' => 13],
        ]);
        $category = ParticipantCategory::factory()->create([
            'event_id' => $event->id,
            'name' => 'VIP Delegate',
            'is_paid' => true,
            'price' => 5000,
            'currency' => 'NPR',
        ]);

        $this->line("[1] Event: <fg=cyan>{$event->name}</>");
        $this->line("    Category: {$category->name} @ NPR {$category->price}");

        $reg = Registration::create([
            'event_id' => $event->id,
            'category_id' => $category->id,
            'registration_source' => 'self',
            'approval_status' => 'approved',
            'name' => 'Hari Bahadur',
            'email' => 'hari@example.com',
            'phone' => '+9779800000001',
            'consented_at' => now(),
        ]);
        $this->line("[2] Registration #{$reg->id}: {$reg->name} (Guest #{$reg->guest_number})");

        $redirector = app(PaymentRedirector::class);
        $html = $redirector->initiate($reg, $event, $category);
        preg_match_all("/<input type='hidden' name='([^']+)' value='([^']+)'>/", $html, $matches);
        $fields = array_combine($matches[1], $matches[2]);
        preg_match("/action='([^']+)'/", $html, $m);
        $actionUrl = $m[1] ?? '?';

        $this->line('[3] Auto-submit form:');
        $this->table(
            ['Field', 'Length', 'Max', 'Status', 'Value'],
            collect($fields)->map(function ($v, $k) {
                $max = ['MERCHANTID' => 20, 'APPID' => 15, 'APPNAME' => 30, 'TXNID' => 20, 'TXNDATE' => 10, 'TXNCRNCY' => 3, 'TXNAMT' => 20, 'REFERENCEID' => 20, 'REMARKS' => 50, 'PARTICULARS' => 100, 'TOKEN' => 512][$k] ?? 0;
                $display = $k === 'TOKEN' ? substr($v, 0, 30).'...' : $v;
                $status = strlen($v) <= $max ? '<fg=green>OK</>' : "<fg=red>OVER {$max}</>";

                return [$k, strlen($v), $max, $status, $display];
            })->values()->toArray()
        );
        $this->line("    Action URL: <fg=yellow>{$actionUrl}</>");

        $payment = Payment::where('transaction_id', $fields['TXNID'])->first();
        $this->line("[4] Payment row created: ID={$payment->id}, status=initiated, amount_paisa={$payment->amount_paisa}");

        if (! $live) {
            Http::fake([
                '*/validatetxn' => Http::response([
                    'status' => 'SUCCESS',
                    'statusDesc' => 'TRANSACTION SUCCESSFUL',
                    'txnAmt' => $fields['TXNAMT'],
                    'txnId' => '987654321',
                    'merchantId' => (int) $fields['MERCHANTID'],
                    'appId' => $fields['APPID'],
                    'referenceId' => $fields['TXNID'],
                ], 200),
                '*/gettxndetail' => Http::response([
                    'status' => 'SUCCESS',
                    'statusDesc' => 'TRANSACTION SUCCESSFUL',
                    'txnAmt' => $fields['TXNAMT'],
                    'txnId' => '987654321',
                    'batchId' => 'BATCH20260623001',
                    'debitBankCode' => '2501',
                    'chargeAmt' => 500,
                    'chargeLiability' => 'CG',
                    'refId' => $fields['REFERENCEID'],
                    'remarks' => $fields['REMARKS'],
                    'particulars' => $fields['PARTICULARS'],
                    'creditStatus' => '000',
                ], 200),
            ]);
        }

        $this->line('[5] '.($live ? 'Calling NCHL UAT validatetxn...' : 'Calling mocked NCHL validatetxn...'));

        $service = app(ConnectIPSService::class);
        $result = $service->validatePayment($payment);
        $interpreted = $service->interpretValidationResult($payment, $result);

        $this->line('    Raw: '.json_encode($result, JSON_UNESCAPED_SLASHES));
        $this->line("    Interpreted: <fg=cyan>{$interpreted['outcome']}</>");

        $payment->markAsSuccess($interpreted['gateway_txn_id'] ?? $payment->transaction_id, $result);

        if ($interpreted['outcome'] === 'success' && ! $live) {
            $detail = $service->getTransactionDetail($payment);
            $payment->recordReconciliationDetails($detail);
        }

        $payment->refresh();
        $reg->refresh();

        $this->table(
            ['Field', 'Value'],
            [
                ['payment_status', $payment->payment_status],
                ['gateway_txn_id', $payment->gateway_txn_id ?? '—'],
                ['batch_id', $payment->batch_id ?? '—'],
                ['debit_bank_code', $payment->debit_bank_code ?? '—'],
                ['charge_amount_paisa', $payment->charge_amount_paisa ?? '—'],
                ['credit_status', $payment->credit_status ?? '—'],
                ['invoice_number', $payment->invoice_number ?? '—'],
                ['isMerchantCreditSuccess', $payment->isMerchantCreditSuccess() ? 'YES' : 'NO'],
                ['registration.payment_status', $reg->payment_status ?? 'null'],
            ]
        );

        $event->delete();
        $category->delete();
        $reg->delete();
        $payment->delete();

        $this->info("\n[6] Flow complete.\n");

        return self::SUCCESS;
    }
}
