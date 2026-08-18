<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\SendSms;
use App\Jobs\SendBulkSMS;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class SendSmsTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::factory()->create(['status' => 'published']);
        $this->actingAs(User::factory()->create(['role' => Role::SuperAdmin->value]));
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function numbers(): array
    {
        return [
            'plain mobile' => ['9851079793', true],
            'leading zero' => ['09851079793', true],
            'country code' => ['+9779851079793', true],
            'spaced' => ['+977 985-107-9793', true],
            'landline' => ['01-5444477', false],
            'too short' => ['98510', false],
            'empty' => ['', false],
            'words' => ['not a number', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('numbers')]
    public function test_only_mobile_numbers_count_as_reachable(string $phone, bool $expected): void
    {
        $this->assertSame($expected, Registration::isMobileNumber($phone));
    }

    public function test_landlines_are_reported_as_unreachable(): void
    {
        Registration::factory()->create(['event_id' => $this->event->id, 'phone' => '9851079793', 'approval_status' => 'approved']);
        Registration::factory()->create(['event_id' => $this->event->id, 'phone' => '01-5444477', 'approval_status' => 'approved']);
        Registration::factory()->create(['event_id' => $this->event->id, 'phone' => null, 'approval_status' => 'approved']);

        $page = new SendSms;
        $page->data = ['event_id' => $this->event->id, 'approved_only' => true];

        $this->assertSame(1, $page->audience()['reachable']);
        $this->assertSame(2, $page->audience()['unreachable']);
    }

    public function test_sending_queues_only_the_reachable_guests(): void
    {
        Queue::fake();

        $mobile = Registration::factory()->create(['event_id' => $this->event->id, 'phone' => '9851079793', 'approval_status' => 'approved']);
        Registration::factory()->create(['event_id' => $this->event->id, 'phone' => '01-5444477', 'approval_status' => 'approved']);

        Livewire::test(SendSms::class)
            ->set('data.event_id', $this->event->id)
            ->set('data.approved_only', true)
            ->set('data.message', 'Gates open at 10:00.')
            ->callAction('send');

        Queue::assertPushed(SendBulkSMS::class, fn (SendBulkSMS $job) => $job->registrationIds === [$mobile->id]
            && $job->message === 'Gates open at 10:00.');
    }

    public function test_message_length_is_counted_in_parts(): void
    {
        $page = new SendSms;

        $page->data = ['message' => ''];
        $this->assertSame(0, $page->segments());

        $page->data = ['message' => str_repeat('a', 160)];
        $this->assertSame(1, $page->segments());

        $page->data = ['message' => str_repeat('a', 161)];
        $this->assertSame(2, $page->segments());
    }

    public function test_the_page_is_closed_to_roles_that_cannot_send(): void
    {
        foreach ([Role::RegistrationStaff, Role::ScannerStaff, Role::Finance, Role::Viewer] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role->value]));

            $this->assertFalse(SendSms::canAccess(), "{$role->value} must not send SMS");
        }
    }
}
