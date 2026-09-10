<?php

namespace Tests\Feature;

use App\Filament\Resources\RegistrationResource\Pages\ListRegistrations;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DownloadTicketsBulkActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_downloading_tickets_zips_pdfs_named_after_the_guest(): void
    {
        $event = Event::factory()->create(['status' => 'published']);
        session(['active_event_id' => $event->id]);

        $alice = Registration::factory()->create(['event_id' => $event->id, 'name' => 'Alice Sharma', 'salutation' => null]);
        $bob = Registration::factory()->create(['event_id' => $event->id, 'name' => 'Bob/Rai', 'salutation' => null]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $component = Livewire::test(ListRegistrations::class)
            ->callTableBulkAction('download_tickets', [$alice, $bob])
            ->assertFileDownloaded('tickets.zip');

        $zipPath = tempnam(sys_get_temp_dir(), 'test-tickets-check').'.zip';
        file_put_contents($zipPath, base64_decode(data_get($component->effects, 'download.content')));

        $zip = new \ZipArchive;
        $zip->open($zipPath);

        $this->assertNotFalse($zip->locateName('Alice Sharma.pdf'));
        // "/" is stripped since it is unsafe in a filename.
        $this->assertNotFalse($zip->locateName('BobRai.pdf'));
        $this->assertSame(2, $zip->numFiles);

        $zip->close();
        unlink($zipPath);
    }

    public function test_downloading_a_single_ticket_returns_the_pdf_directly(): void
    {
        $event = Event::factory()->create(['status' => 'published']);
        session(['active_event_id' => $event->id]);

        $alice = Registration::factory()->create(['event_id' => $event->id, 'name' => 'Alice Sharma', 'salutation' => null]);

        $this->actingAs(User::factory()->create(['role' => 'super_admin']));

        $component = Livewire::test(ListRegistrations::class)
            ->callTableBulkAction('download_tickets', [$alice])
            ->assertFileDownloaded('Alice Sharma.pdf');

        $this->assertSame('application/pdf', data_get($component->effects, 'download.contentType'));
    }
}
