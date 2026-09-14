<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\LabelTemplate;
use App\Models\Registration;
use App\Services\LabelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sevenBySeventyTemplate(Event $event): LabelTemplate
    {
        return LabelTemplate::create([
            'event_id' => $event->id,
            'template_name' => '70x30 ID Label',
            'width' => 70,
            'height' => 30,
            'orientation' => 'portrait',
            'show_qr' => true,
            'show_designation' => true,
            'show_organization' => true,
            'show_category_color' => true,
            'font_size_name' => 16,
            'margin_top' => 2,
            'margin_right' => 3,
            'margin_bottom' => 2,
            'margin_left' => 3,
        ]);
    }

    public function test_id_label_pdf_renders_within_the_70x30_sticker(): void
    {
        $event = Event::factory()->create();
        $template = $this->sevenBySeventyTemplate($event);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'name' => 'A Very Long Guest Name For Layout Testing',
            'organization' => 'A Reasonably Long Organization Name Inc',
        ]);

        $pdf = app(LabelService::class)->generateLabelPdf(collect([$registration]), $template);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    public function test_delivery_label_pdf_renders_within_the_70x30_sticker(): void
    {
        $event = Event::factory()->create();
        $template = $this->sevenBySeventyTemplate($event);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'name' => 'Delivery Test Guest',
            'address' => 'Some fairly long street address, Kathmandu, Bagmati Province, Nepal',
            'pickndrop_order_id' => 'ORD-12345',
        ]);

        $pdf = app(LabelService::class)->generateDeliveryLabelPdf(collect([$registration]), $template);

        $this->assertStringStartsWith('%PDF-', $pdf);
    }

    /** Pulls the base64 QR payload out of rendered label HTML, so tests can compare just the encoded image. */
    private function qrSrc(string $html): ?string
    {
        preg_match('/class="order-qr" src="([^"]+)"/', $html, $matches);

        return $matches[1] ?? null;
    }

    public function test_team_delivery_label_qr_encodes_delivery_id_not_pickndrop_order(): void
    {
        $event = Event::factory()->create();
        $template = $this->sevenBySeventyTemplate($event);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'pickndrop_order_id' => 'ORD-12345',
        ]);

        $teamHtml = app(LabelService::class)->generateDeliverySheetHtml(collect([$registration]), $template, 'team');
        $pndHtml = app(LabelService::class)->generateDeliverySheetHtml(collect([$registration]), $template, 'pnd');

        $this->assertStringContainsString('DNC 2026', $teamHtml);
        $this->assertStringContainsString('PicknDrop', $pndHtml);
        // Different QR payloads (delivery_id vs pickndrop_order_id) render different images.
        $this->assertNotSame($this->qrSrc($teamHtml), $this->qrSrc($pndHtml));
    }

    public function test_pnd_delivery_label_falls_back_to_delivery_id_without_an_order(): void
    {
        $event = Event::factory()->create();
        $template = $this->sevenBySeventyTemplate($event);
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'pickndrop_order_id' => null,
        ]);

        $teamHtml = app(LabelService::class)->generateDeliverySheetHtml(collect([$registration]), $template, 'team');
        $pndHtml = app(LabelService::class)->generateDeliverySheetHtml(collect([$registration]), $template, 'pnd');

        // No courier order yet, so both label types fall back to the same delivery_id QR, only the caption differs.
        $this->assertSame($this->qrSrc($teamHtml), $this->qrSrc($pndHtml));
    }

    public function test_id_label_qr_keeps_the_same_margin_as_the_configured_right_margin(): void
    {
        $event = Event::factory()->create();
        $template = $this->sevenBySeventyTemplate($event);

        $reflection = new \ReflectionClass(LabelService::class);
        $method = $reflection->getMethod('geometry');
        $method->setAccessible(true);

        $geo = $method->invoke(app(LabelService::class), $template);

        // The QR sits `padX` from the right in CSS ("right: {padX}mm"), so the
        // gap between the QR's right edge and the sticker's right edge is padX.
        $this->assertEqualsWithDelta((float) $template->margin_right, $geo['padX'], 0.01);
    }
}
