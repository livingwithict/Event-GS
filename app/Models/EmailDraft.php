<?php

namespace App\Models;

use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDraft extends Model
{
    public const CUSTOM = 'custom';

    public const FORMAT_RICH = 'rich';

    public const FORMAT_HTML = 'html';

    /** What an email is for. Automatic emails of a type use the event's default draft of that type. */
    public const TYPES = [
        'invitation' => 'Invitation',
        'invitation_face_verification' => 'Invitation with face verification',
        'face_verification' => 'Face verification',
        'registration_confirmation' => 'Registration confirmation',
        'payment_success' => 'Payment success',
        'payment_failed' => 'Payment failed',
        'event_reminder' => 'Event reminder',
        'post_event_thank_you' => 'Post-event thank you',
        'urgent_update' => 'Urgent update',
        self::CUSTOM => 'Custom / one-off',
    ];

    /** Types whose ticket PDF is attached by default (guests show it at the entrance). */
    public const TICKET_TYPES = ['invitation', 'invitation_face_verification', 'registration_confirmation', 'payment_success'];

    /** Placeholders usable in subject and body (label shown in the editor). */
    public const MERGE_TAGS = [
        'name' => 'Guest name',
        'event_name' => 'Event name',
        'guest_number' => 'Guest number',
        'category' => 'Category',
        'venue' => 'Venue',
        'date' => 'Event date (Sunday, September 20, 2026)',
        'date_short' => 'Event date (September 20, 2026)',
        'time' => 'Event time',
        'contact_info' => 'Event contact info',
    ];

    /** Extra tags for HTML-format bodies only (links, images and blocks, not text). */
    public const HTML_TAGS = [
        'logo_url' => 'Event logo URL',
        'header_logo_url' => 'Partner logo URL, else event logo',
        'qr_url' => 'Guest QR code image URL',
        'qr_svg' => 'Guest QR code, inline SVG',
        'face_url' => 'Face-verification link',
        'download_url' => 'Ticket download link',
        'payment_amount' => 'Payment amount (NPR)',
        'payment_txn' => 'Payment reference',
    ];

    /** Tags whose value is markup and must not be HTML-escaped. */
    private const RAW_TAGS = ['qr_svg'];

    protected $fillable = [
        'event_id', 'name', 'type', 'from_address', 'from_name', 'reply_to',
        'subject', 'body', 'body_format', 'include_qr', 'include_face_link', 'attach_ticket', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'include_qr' => 'boolean',
            'include_face_link' => 'boolean',
            'attach_ticket' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Only one default per event + type, or automatic sends would pick arbitrarily.
        static::saved(function (EmailDraft $draft) {
            if ($draft->is_default) {
                static::where('event_id', $draft->event_id)
                    ->where('type', $draft->type)
                    ->whereKeyNot($draft->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function isCustom(): bool
    {
        return $this->type === self::CUSTOM;
    }

    public function isHtml(): bool
    {
        return $this->body_format === self::FORMAT_HTML;
    }

    public function usesTag(string $tag): bool
    {
        return str_contains((string) $this->body, '{{'.$tag.'}}');
    }

    /** What Communication.email_type records, so skip-already-sent works per draft. */
    public function communicationType(): string
    {
        return $this->isCustom() ? 'custom:'.$this->id : $this->type;
    }

    /**
     * Everything a draft can interpolate. $extra carries the expensive values
     * (qr_svg, face_url) that the sender only computes when the body uses them.
     *
     * @return array<string, string>
     */
    public function tagValues(Registration $registration, Event $event, array $extra = []): array
    {
        $start = $event->start_datetime ?? $event->event_date;
        $payment = $registration->payment;
        $logo = (string) $event->logoUrl();

        return [
            'name' => $registration->displayName(),
            'event_name' => $event->name,
            'guest_number' => (string) $registration->guest_number,
            'category' => (string) $registration->category?->name,
            'venue' => (string) $event->venue,
            'date' => $start?->format('l, F j, Y') ?? '',
            'date_short' => $start?->format('F j, Y') ?? '',
            'time' => $event->start_datetime?->format('g:i A') ?? '',
            'contact_info' => $event->contact_info ?: 'events@ictfoundation.org.np',
            'logo_url' => $logo,
            'header_logo_url' => $event->partnerLogoUrls()[0] ?? $logo,
            'qr_url' => $registration->qr_hash ? route('ticket.qr-image', $registration->qr_hash) : '',
            'download_url' => $registration->qr_hash ? config('app.url').'/ticket/'.$registration->qr_hash.'/download' : '',
            'payment_amount' => $payment ? number_format($payment->getAmountRupees(), 2) : '',
            'payment_txn' => (string) $payment?->transaction_id,
            'qr_svg' => '',
            'face_url' => '',
            ...$extra,
        ];
    }

    /**
     * {{tag}} substitution plus {{#if tag}}…{{/if}} / {{#unless tag}}…{{/unless}}
     * blocks (not nestable). Deliberately not Blade: draft text is typed by
     * dashboard users and must never be able to run code.
     *
     * @param  array<string, string>  $values
     */
    public static function fillTags(string $html, array $values): string
    {
        $html = preg_replace_callback(
            '/\{\{#(if|unless)\s+(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
            fn ($m) => ($m[1] === 'if') === filled($values[$m[2]] ?? null) ? $m[3] : '',
            $html,
        );

        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($values) {
            if (! array_key_exists($m[1], $values)) {
                return $m[0];
            }

            return in_array($m[1], self::RAW_TAGS, true) ? $values[$m[1]] : e($values[$m[1]]);
        }, $html);
    }

    /** Fills {{tags}} in $subject (default: the draft's own subject); null when there is none. */
    public function renderSubject(Registration $registration, Event $event, ?string $subject = null): ?string
    {
        $subject ??= $this->subject;

        if (! $subject) {
            return null;
        }

        $values = $this->tagValues($registration, $event);

        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => $values[$m[1]] ?? $m[0], $subject);
    }

    public function renderBody(Registration $registration, Event $event, array $extra = []): string
    {
        $values = $this->tagValues($registration, $event, $extra);

        if ($this->isHtml()) {
            return self::fillTags((string) $this->body, $values);
        }

        return RichContentRenderer::make((string) $this->body)->mergeTags($values)->toHtml();
    }
}
