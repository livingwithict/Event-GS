<?php

namespace Tests\Feature;

use App\Models\EmailDraft;
use App\Models\Event;
use App\Models\Registration;
use App\Services\CommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailDraftTest extends TestCase
{
    use RefreshDatabase;

    private function lastMessage()
    {
        $messages = Mail::mailer()->getSymfonyTransport()->messages();

        return $messages->last()->getOriginalMessage();
    }

    public function test_default_draft_sets_sender_and_subject_for_automatic_sends(): void
    {
        $event = Event::factory()->create(['mail_domain' => 'acme.test']);
        $reg = Registration::factory()->create(['event_id' => $event->id, 'email' => 'g@example.com']);
        EmailDraft::create([
            'event_id' => $event->id, 'name' => 'Reminder', 'type' => 'event_reminder', 'is_default' => true,
            'from_address' => 'hello@acme.test', 'from_name' => 'Acme Team', 'subject' => 'See you, {{name}}',
            'body' => '<p>Hi {{name}}</p>{{#if venue}}<p>At {{venue}}</p>{{/if}}{{#unless venue}}<p>NOVENUE</p>{{/unless}}', 'body_format' => 'html',
        ]);

        app(CommunicationService::class)->sendEmail($reg, $event, 'ignored', 'event_reminder');

        $message = $this->lastMessage();
        $this->assertSame('hello@acme.test', $message->getFrom()[0]->getAddress());
        $this->assertSame('Acme Team', $message->getFrom()[0]->getName());
        $this->assertSame('See you, '.$reg->displayName(), $message->getSubject());
    }

    public function test_html_draft_fills_tags_blocks_and_escapes_values(): void
    {
        $event = Event::factory()->create(['venue' => '']);
        $reg = Registration::factory()->create(['event_id' => $event->id, 'name' => '<b>Eve</b>']);
        $draft = EmailDraft::create([
            'event_id' => $event->id, 'name' => 'H', 'type' => 'custom', 'body_format' => 'html',
            'body' => '<p>{{name}}</p>{{#if venue}}HAS-VENUE{{/if}}{{#unless venue}}NO-VENUE{{/unless}}{{secret}}',
        ]);

        $html = $draft->renderBody($reg, $event);

        $this->assertStringContainsString('&lt;b&gt;Eve&lt;/b&gt;', $html);
        $this->assertStringContainsString('NO-VENUE', $html);
        $this->assertStringNotContainsString('HAS-VENUE', $html);
        $this->assertStringContainsString('{{secret}}', $html); // unknown tags stay literal
    }

    public function test_event_without_drafts_gets_the_neutral_default_email(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id, 'email' => 'g@example.com']);

        $comm = app(CommunicationService::class)->sendEmail($reg, $event, 'Reminder', 'event_reminder');

        $this->assertSame('sent', $comm->status);
        $this->assertStringContainsString($event->name, $this->lastMessage()->getHtmlBody());
    }

    public function test_event_domain_replaces_global_domain_when_no_draft(): void
    {
        $event = Event::factory()->create(['mail_domain' => 'acme.test']);
        $reg = Registration::factory()->create(['event_id' => $event->id, 'email' => 'g@example.com']);

        app(CommunicationService::class)->sendEmail($reg, $event, 'Hi', 'event_reminder');

        $this->assertSame('acme.test', explode('@', $this->lastMessage()->getFrom()[0]->getAddress())[1]);
    }

    public function test_custom_draft_renders_merge_tags_in_body_and_logs_custom_type(): void
    {
        $event = Event::factory()->create();
        $reg = Registration::factory()->create(['event_id' => $event->id, 'email' => 'g@example.com']);
        $draft = EmailDraft::create([
            'event_id' => $event->id, 'name' => 'Hello', 'type' => 'custom', 'subject' => 'Hi {{name}}',
            'body' => '<p>Dear <span data-type="mergeTag" data-id="name"></span>, welcome to <span data-type="mergeTag" data-id="event_name"></span>.</p>',
        ]);

        $comm = app(CommunicationService::class)->sendEmail($reg, $event, 'x', $draft->communicationType(), $draft);

        $this->assertSame("custom:{$draft->id}", $comm->email_type);
        $this->assertSame('sent', $comm->status);
        $html = $this->lastMessage()->getHtmlBody();
        $this->assertStringContainsString($reg->displayName(), $html);
        $this->assertStringContainsString($event->name, $html);
    }

    public function test_api_key_is_encrypted_and_hidden(): void
    {
        $event = Event::factory()->create(['resend_api_key' => 're_secret']);

        $this->assertSame('re_secret', $event->fresh()->resend_api_key);
        $this->assertNotSame('re_secret', $event->getRawOriginal('resend_api_key'));
        $this->assertArrayNotHasKey('resend_api_key', $event->fresh()->toArray());
    }

    public function test_admin_pages_render(): void
    {
        $admin = \App\Models\User::factory()->create(['role' => 'super_admin']);
        $event = Event::factory()->create();
        $draft = EmailDraft::create(['event_id' => $event->id, 'name' => 'A', 'type' => 'custom', 'body' => '<p>x</p>']);

        foreach (['/admin/email-drafts', '/admin/email-drafts/create', "/admin/email-drafts/{$draft->id}/edit", "/admin/events/{$event->id}/edit"] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_only_one_default_per_event_and_type(): void
    {
        $event = Event::factory()->create();
        $a = EmailDraft::create(['event_id' => $event->id, 'name' => 'A', 'type' => 'invitation', 'is_default' => true]);
        $b = EmailDraft::create(['event_id' => $event->id, 'name' => 'B', 'type' => 'invitation', 'is_default' => true]);

        $this->assertFalse($a->fresh()->is_default);
        $this->assertTrue($b->fresh()->is_default);
    }
}
