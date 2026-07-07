<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Payment\ConnectIPSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPath = tempnam(sys_get_temp_dir(), 'pk_');
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $pem);
        file_put_contents($keyPath, $pem);

        config()->set('connectips.base_url', 'https://uat.connectips.com:7443');
        config()->set('connectips.merchant_id', '550');
        config()->set('connectips.app_id', 'MER-550-APP-1');
        config()->set('connectips.app_name', 'Test Merchant');
        config()->set('connectips.app_password', 'test-pass');
        config()->set('connectips.private_key_path', $keyPath);
        config()->set('connectips.private_key_format', 'pem');

        $this->keyPath = $keyPath;
    }

    protected function tearDown(): void
    {
        if (file_exists($this->keyPath)) {
            unlink($this->keyPath);
        }
        parent::tearDown();
    }

    private string $keyPath;

    public function test_interpret_validation_success_outcome(): void
    {
        $payment = $this->makePendingPayment();
        $service = app(ConnectIPSService::class);

        $result = $service->interpretValidationResult($payment, [
            'status' => 'SUCCESS',
            'statusDesc' => 'TRANSACTION SUCCESSFUL',
            'txnAmt' => $payment->amount_paisa,
            'txnId' => '12345',
        ]);

        $this->assertEquals('success', $result['outcome']);
        $this->assertFalse($result['amount_mismatch']);
        $this->assertEquals('12345', $result['gateway_txn_id']);
    }

    public function test_interpret_validation_failed_outcome(): void
    {
        $payment = $this->makePendingPayment();
        $service = app(ConnectIPSService::class);

        $result = $service->interpretValidationResult($payment, [
            'status' => 'FAILED',
            'statusDesc' => 'TRANSACTION UNSUCCESSFUL',
            'txnAmt' => $payment->amount_paisa,
        ]);

        $this->assertEquals('failed', $result['outcome']);
    }

    public function test_interpret_validation_not_found_outcome(): void
    {
        $payment = $this->makePendingPayment();
        $service = app(ConnectIPSService::class);

        $result = $service->interpretValidationResult($payment, [
            'status' => 'ERROR',
            'statusDesc' => 'TRANSACTION NOT FOUND',
            'txnAmt' => $payment->amount_paisa,
        ]);

        $this->assertEquals('failed', $result['outcome']);
    }

    public function test_interpret_validation_incomplete_leaves_pending(): void
    {
        $payment = $this->makePendingPayment();
        $service = app(ConnectIPSService::class);

        $result = $service->interpretValidationResult($payment, [
            'status' => 'ERROR',
            'statusDesc' => 'TRANSACTION INCOMPLETE',
            'txnAmt' => $payment->amount_paisa,
        ]);

        $this->assertEquals('pending', $result['outcome']);
    }

    public function test_interpret_validation_detects_amount_mismatch(): void
    {
        $payment = $this->makePendingPayment();
        $service = app(ConnectIPSService::class);

        $result = $service->interpretValidationResult($payment, [
            'status' => 'SUCCESS',
            'statusDesc' => 'TRANSACTION SUCCESSFUL',
            'txnAmt' => $payment->amount_paisa + 100,
        ]);

        $this->assertTrue($result['amount_mismatch']);
    }

    public function test_payment_success_route_redirects_on_successful_validation(): void
    {
        $event = Event::factory()->create(['slug' => 'payflow-test']);
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'guest_number' => 'EVT-G-000001',
        ]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 50000,
            'currency' => 'NPR',
            'transaction_id' => 'TXNTEST01',
            'payment_status' => 'initiated',
        ]);

        Http::fake([
            '*/validatetxn' => Http::response([
                'status' => 'SUCCESS',
                'statusDesc' => 'TRANSACTION SUCCESSFUL',
                'txnAmt' => 50000,
                'txnId' => 99999,
            ], 200),
            '*/gettxndetail' => Http::response([
                'status' => 'SUCCESS',
                'statusDesc' => 'TRANSACTION SUCCESSFUL',
                'txnAmt' => 50000,
                'txnId' => 99999,
                'batchId' => 'BATCH123',
                'debitBankCode' => '2501',
                'chargeAmt' => 200,
                'creditStatus' => '000',
            ], 200),
        ]);

        $response = $this->get("/event/{$event->slug}/payment/success?TXNID=TXNTEST01");
        $response->assertRedirect(route('register.success', ['slug' => $event->slug]));

        $payment->refresh();
        $this->assertEquals('success', $payment->payment_status);
        $this->assertEquals(99999, (int) $payment->gateway_txn_id);
        $this->assertEquals('BATCH123', $payment->batch_id);
        $this->assertEquals('2501', $payment->debit_bank_code);
        $this->assertEquals(200, $payment->charge_amount_paisa);
        $this->assertEquals('000', $payment->credit_status);
    }

    public function test_payment_success_route_shows_pending_on_incomplete(): void
    {
        $event = Event::factory()->create(['slug' => 'incomplete-test']);
        $reg = Registration::factory()->create(['event_id' => $event->id]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 50000,
            'currency' => 'NPR',
            'transaction_id' => 'TXINCOMP1',
            'payment_status' => 'initiated',
        ]);

        Http::fake([
            '*/validatetxn' => Http::response([
                'status' => 'ERROR',
                'statusDesc' => 'TRANSACTION INCOMPLETE',
                'txnAmt' => 50000,
            ], 200),
        ]);

        $response = $this->get("/event/{$event->slug}/payment/success?TXNID=TXINCOMP1");

        $response->assertOk();
        $response->assertViewIs('register.payment-pending');

        $payment->refresh();
        $this->assertEquals('initiated', $payment->payment_status);
    }

    public function test_payment_success_route_shows_failed_view_on_failure(): void
    {
        $event = Event::factory()->create(['slug' => 'fail-test']);
        $reg = Registration::factory()->create(['event_id' => $event->id]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 50000,
            'currency' => 'NPR',
            'transaction_id' => 'TXFAIL01',
            'payment_status' => 'initiated',
        ]);

        Http::fake([
            '*/validatetxn' => Http::response([
                'status' => 'FAILED',
                'statusDesc' => 'TRANSACTION UNSUCCESSFUL',
                'txnAmt' => 50000,
            ], 200),
        ]);

        $response = $this->get("/event/{$event->slug}/payment/success?TXNID=TXFAIL01");

        $response->assertOk();
        $response->assertViewIs('register.payment-failed');

        $payment->refresh();
        $this->assertEquals('failed', $payment->payment_status);
    }

    public function test_payment_success_amount_mismatch_marks_failed(): void
    {
        $event = Event::factory()->create(['slug' => 'mismatch-test']);
        $reg = Registration::factory()->create(['event_id' => $event->id]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 50000,
            'currency' => 'NPR',
            'transaction_id' => 'TXMM01',
            'payment_status' => 'initiated',
        ]);

        Http::fake([
            '*/validatetxn' => Http::response([
                'status' => 'SUCCESS',
                'statusDesc' => 'TRANSACTION SUCCESSFUL',
                'txnAmt' => 99000,
                'txnId' => 11111,
            ], 200),
        ]);

        $response = $this->get("/event/{$event->slug}/payment/success?TXNID=TXMM01");

        $payment->refresh();
        $this->assertEquals('success', $payment->payment_status);
    }

    public function test_payment_expire_command_clears_stuck_initiated_payments(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id]);

        $stuck = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 100,
            'currency' => 'NPR',
            'transaction_id' => 'STUCK001',
            'payment_status' => 'initiated',
            'expires_at' => now()->subMinutes(5),
        ]);

        $fresh = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 200,
            'currency' => 'NPR',
            'transaction_id' => 'FRESH001',
            'payment_status' => 'initiated',
            'expires_at' => now()->addMinutes(20),
        ]);

        $this->artisan('payment:expire')->assertSuccessful();

        $this->assertEquals('expired', $stuck->fresh()->payment_status);
        $this->assertEquals('initiated', $fresh->fresh()->payment_status);
    }

    public function test_payment_model_records_reconciliation_details(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 100,
            'currency' => 'NPR',
            'transaction_id' => 'RECON001',
            'payment_status' => 'success',
        ]);

        $payment->recordReconciliationDetails([
            'batchId' => 'BATCH999',
            'debitBankCode' => '2501',
            'chargeAmt' => 200,
            'creditStatus' => '000',
            'txnId' => 12345,
        ]);

        $payment->refresh();
        $this->assertEquals('BATCH999', $payment->batch_id);
        $this->assertEquals('2501', $payment->debit_bank_code);
        $this->assertEquals(200, $payment->charge_amount_paisa);
        $this->assertEquals('000', $payment->credit_status);
        $this->assertEquals(12345, (int) $payment->gateway_txn_id);
    }

    public function test_is_merchant_credit_success_recognizes_known_success_codes(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id]);

        foreach (['000', '999', 'DEFER'] as $code) {
            $p = Payment::create([
                'registration_id' => $reg->id,
                'event_id' => $event->id,
                'amount_paisa' => 100,
                'currency' => 'NPR',
                'transaction_id' => 'CC'.$code,
                'payment_status' => 'success',
                'credit_status' => $code,
            ]);
            $this->assertTrue($p->isMerchantCreditSuccess(), "creditStatus $code should be success");
        }

        $fail = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 100,
            'currency' => 'NPR',
            'transaction_id' => 'CCFAIL',
            'payment_status' => 'success',
            'credit_status' => '050',
        ]);
        $this->assertFalse($fail->isMerchantCreditSuccess());
    }

    public function test_payment_mark_as_refunded_updates_status(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 100,
            'currency' => 'NPR',
            'transaction_id' => 'REFUND01',
            'payment_status' => 'success',
        ]);

        $payment->markAsRefunded(1, ['method' => 'manual']);

        $this->assertEquals('refunded', $payment->fresh()->payment_status);
        $this->assertTrue($payment->fresh()->isRefunded());
    }

    private function makePendingPayment(): Payment
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id]);

        return Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 50000,
            'currency' => 'NPR',
            'transaction_id' => Payment::generateTransactionId(),
            'payment_status' => 'initiated',
        ]);
    }
}
