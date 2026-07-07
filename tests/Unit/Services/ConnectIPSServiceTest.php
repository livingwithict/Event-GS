<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\Payment;
use App\Models\Registration;
use App\Services\Payment\ConnectIPSService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConnectIPSServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $tempKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempKeyPath = $this->generateTestKey();

        config()->set('connectips.base_url', 'https://uat.connectips.com:7443');
        config()->set('connectips.merchant_id', '550');
        config()->set('connectips.app_id', 'MER-550-APP-1');
        config()->set('connectips.app_name', 'Test Merchant');
        config()->set('connectips.app_password', 'test-pass');
        config()->set('connectips.private_key_path', $this->tempKeyPath);
        config()->set('connectips.private_key_passphrase', '');
        config()->set('connectips.private_key_format', 'pem');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempKeyPath)) {
            unlink($this->tempKeyPath);
        }

        parent::tearDown();
    }

    public function test_generate_token_string_format_matches_nchl_spec(): void
    {
        $service = new ConnectIPSService;

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('signWithPrivateKey');
        $method->setAccessible(true);

        $params = [
            'MERCHANTID' => '550',
            'APPID' => 'MER-550-APP-1',
            'APPNAME' => 'Test',
            'TXNID' => 'PAY123',
            'TXNDATE' => '15-03-2026',
            'TXNCRNCY' => 'NPR',
            'TXNAMT' => 50000,
            'REFERENCEID' => 'REF-001',
            'REMARKS' => 'RMKS-001',
            'PARTICULARS' => 'PART-001',
        ];

        $expectedMessage = 'MERCHANTID=550,APPID=MER-550-APP-1,APPNAME=Test,TXNID=PAY123,TXNDATE=15-03-2026,TXNCRNCY=NPR,TXNAMT=50000,REFERENCEID=REF-001,REMARKS=RMKS-001,PARTICULARS=PART-001,TOKEN=TOKEN';

        $signature = $service->generateToken($params);

        $this->assertIsString($signature);
        $this->assertNotEmpty($signature);
        $this->assertEquals(
            $this->base64Sha256RsaSign($expectedMessage, $this->tempKeyPath),
            $signature
        );
    }

    public function test_generate_validation_token_format(): void
    {
        $service = new ConnectIPSService;

        $params = [
            'MERCHANTID' => '550',
            'APPID' => 'MER-550-APP-1',
            'REFERENCEID' => 'PAY123',
            'TXNAMT' => 50000,
        ];

        $expected = 'MERCHANTID=550,APPID=MER-550-APP-1,REFERENCEID=PAY123,TXNAMT=50000';
        $signature = $service->generateValidationToken($params);

        $this->assertEquals(
            $this->base64Sha256RsaSign($expected, $this->tempKeyPath),
            $signature
        );
    }

    public function test_clip_truncates_remarks_to_50_chars(): void
    {
        $service = new ConnectIPSService;

        $long = str_repeat('A', 100);

        $this->assertEquals(50, mb_strlen($service->clip($long, ConnectIPSService::REMARKS_MAX)));
    }

    public function test_clip_truncates_particulars_to_100_chars(): void
    {
        $service = new ConnectIPSService;

        $long = str_repeat('B', 200);

        $this->assertEquals(100, mb_strlen($service->clip($long, ConnectIPSService::PARTICULARS_MAX)));
    }

    public function test_clip_truncates_reference_id_to_20_chars(): void
    {
        $service = new ConnectIPSService;

        $long = str_repeat('C', 50);

        $this->assertEquals(20, mb_strlen($service->clip($long, ConnectIPSService::REFERENCE_ID_MAX)));
    }

    public function test_payment_transaction_id_is_at_most_20_chars(): void
    {
        $txnId = Payment::generateTransactionId();

        $this->assertLessThanOrEqual(20, strlen($txnId));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]+$/', $txnId);
    }

    public function test_payment_transaction_id_is_unique_across_calls(): void
    {
        $a = Payment::generateTransactionId();
        usleep(1100000);
        $b = Payment::generateTransactionId();

        $this->assertNotEquals($a, $b);
    }

    public function test_initiate_payment_renders_auto_submit_form_with_all_required_fields(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'guest_number' => 'EVT-G-000001',
        ]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 50000,
            'currency' => 'NPR',
            'transaction_id' => Payment::generateTransactionId(),
            'payment_status' => 'pending',
        ]);

        $service = new ConnectIPSService;
        $html = $service->initiatePayment($payment);

        $this->assertStringContainsString('connectipswebgw/loginpage', $html);
        $this->assertStringContainsString('name=\'MERCHANTID\'', $html);
        $this->assertStringContainsString('name=\'APPID\'', $html);
        $this->assertStringContainsString('name=\'APPNAME\'', $html);
        $this->assertStringContainsString('name=\'TXNID\'', $html);
        $this->assertStringContainsString('name=\'TXNDATE\'', $html);
        $this->assertStringContainsString('name=\'TXNCRNCY\'', $html);
        $this->assertStringContainsString('name=\'TXNAMT\'', $html);
        $this->assertStringContainsString('name=\'REFERENCEID\'', $html);
        $this->assertStringContainsString('name=\'REMARKS\'', $html);
        $this->assertStringContainsString('name=\'PARTICULARS\'', $html);
        $this->assertStringContainsString('name=\'TOKEN\'', $html);
        $this->assertStringContainsString('document.getElementById(\'paymentForm\').submit()', $html);

        $this->assertEquals('initiated', $payment->fresh()->payment_status);
    }

    public function test_initiate_payment_clips_long_event_name_in_remarks(): void
    {
        $event = Event::factory()->create(['name' => str_repeat('X', 200)]);
        $reg = Registration::factory()->create([
            'event_id' => $event->id,
            'guest_number' => 'EVT-G-000001',
        ]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 50000,
            'currency' => 'NPR',
            'transaction_id' => Payment::generateTransactionId(),
            'payment_status' => 'pending',
        ]);

        $service = new ConnectIPSService;
        $html = $service->initiatePayment($payment);

        preg_match("/name='REMARKS' value='([^']*)'/", $html, $m);
        $this->assertNotEmpty($m);
        $this->assertLessThanOrEqual(50, mb_strlen(html_entity_decode($m[1], ENT_QUOTES)));
    }

    public function test_initiate_payment_throws_when_private_key_missing(): void
    {
        config()->set('connectips.private_key_path', '/nonexistent/path/key.pem');

        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 100,
            'currency' => 'NPR',
            'transaction_id' => Payment::generateTransactionId(),
            'payment_status' => 'pending',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/private key not found/');

        (new ConnectIPSService)->initiatePayment($payment);
    }

    public function test_pfx_format_fails_loudly_when_pkcs12_invalid(): void
    {
        $badPfx = tempnam(sys_get_temp_dir(), 'pfx_');
        file_put_contents($badPfx, 'not-a-real-pfx');

        config()->set('connectips.private_key_path', $badPfx);
        config()->set('connectips.private_key_format', 'pfx');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/failed to load private key/');

        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id]);
        $payment = Payment::create([
            'registration_id' => $reg->id,
            'event_id' => $event->id,
            'amount_paisa' => 100,
            'currency' => 'NPR',
            'transaction_id' => Payment::generateTransactionId(),
            'payment_status' => 'pending',
        ]);

        try {
            (new ConnectIPSService)->initiatePayment($payment);
        } finally {
            unlink($badPfx);
        }
    }

    private function generateTestKey(): string
    {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $this->assertNotFalse($res, 'openssl_pkey_new failed');

        openssl_pkey_export($res, $pem);

        $path = tempnam(sys_get_temp_dir(), 'pk_');
        file_put_contents($path, $pem);

        return $path;
    }

    private function base64Sha256RsaSign(string $message, string $keyPath): string
    {
        $key = openssl_pkey_get_private(file_get_contents($keyPath));
        $this->assertNotFalse($key);

        $sig = '';
        $this->assertTrue(openssl_sign($message, $sig, $key, OPENSSL_ALGO_SHA256));
        openssl_free_key($key);

        return base64_encode($sig);
    }
}
