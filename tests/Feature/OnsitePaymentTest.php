<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnsitePaymentTest extends TestCase
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

    public function test_onsite_registration_with_gateway_payment_creates_payment_and_renders_redirect(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $event = Event::factory()->create([
            'settings' => array_merge(Event::factory()->make()->settings ?? [], [
                'enable_payment' => true,
                'tax_rate' => 0,
            ]),
        ]);
        $manager->assignedEvents()->attach($event->id);
        $category = ParticipantCategory::factory()->create([
            'event_id' => $event->id,
            'is_paid' => true,
            'price' => 1000,
        ]);

        $response = $this->actingAs($manager)
            ->post("/admin/onsite-register/{$event->id}", [
                'name' => 'Walk-in User',
                'email' => 'walkin@test.com',
                'phone' => '+9779800000001',
                'category_id' => $category->id,
                'payment_method' => 'gateway',
            ]);

        $response->assertOk();
        $this->assertStringContainsString('connectipswebgw/loginpage', $response->getContent());

        $reg = Registration::where('email', 'walkin@test.com')->first();
        $this->assertNotNull($reg);
        $this->assertEquals('pending', $reg->payment_status);

        $payment = Payment::where('registration_id', $reg->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals(100000, $payment->amount_paisa);
        $this->assertEquals('initiated', $payment->payment_status);
    }

    public function test_onsite_registration_with_cash_payment_creates_no_payment(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $event = Event::factory()->create([
            'settings' => ['enable_payment' => true, 'tax_rate' => 0],
        ]);
        $manager->assignedEvents()->attach($event->id);
        $category = ParticipantCategory::factory()->create([
            'event_id' => $event->id,
            'is_paid' => true,
            'price' => 1000,
        ]);

        $response = $this->actingAs($manager)
            ->post("/admin/onsite-register/{$event->id}", [
                'name' => 'Cash User',
                'email' => 'cash@test.com',
                'phone' => '+9779800000002',
                'category_id' => $category->id,
                'payment_method' => 'cash',
            ]);

        $response->assertRedirect("/admin/onsite-register/{$event->id}");

        $reg = Registration::where('email', 'cash@test.com')->first();
        $this->assertNotNull($reg);
        $this->assertNull($reg->payment_status);
        $this->assertEquals(0, Payment::where('registration_id', $reg->id)->count());
    }

    public function test_onsite_registration_with_no_payment_method_for_paid_category_skips_gateway(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $event = Event::factory()->create([
            'settings' => ['enable_payment' => true, 'tax_rate' => 0],
        ]);
        $manager->assignedEvents()->attach($event->id);
        $category = ParticipantCategory::factory()->create([
            'event_id' => $event->id,
            'is_paid' => true,
            'price' => 1000,
        ]);

        $response = $this->actingAs($manager)
            ->post("/admin/onsite-register/{$event->id}", [
                'name' => 'Complimentary User',
                'email' => 'comp@test.com',
                'phone' => '+9779800000003',
                'category_id' => $category->id,
                'payment_method' => 'none',
            ]);

        $response->assertRedirect("/admin/onsite-register/{$event->id}");

        $reg = Registration::where('email', 'comp@test.com')->first();
        $this->assertNotNull($reg);
        $this->assertNull($reg->payment_status);
    }

    public function test_onsite_registration_rejects_non_authorized_role(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $event = Event::factory()->create();

        $this->actingAs($viewer)
            ->post("/admin/onsite-register/{$event->id}", [
                'name' => 'No Auth',
                'email' => 'noauth@test.com',
            ])
            ->assertForbidden();
    }
}
