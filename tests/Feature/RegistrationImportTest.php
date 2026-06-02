<?php

use App\Imports\RegistrationsImport;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RegistrationImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_registrations_from_valid_rows(): void
    {
        $event = Event::factory()->create();
        $rows = new Collection([
            ['name' => 'John Doe', 'email' => 'john@test.com', 'phone' => '+9779800000001', 'organization' => 'ICT', 'designation' => '', 'address' => '', 'website' => ''],
            ['name' => 'Jane Smith', 'email' => 'jane@test.com', 'phone' => '+9779800000002', 'organization' => 'Tech Corp', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(2, $import->getImportedCount());
        $this->assertEmpty($import->getErrors());
        $this->assertEquals(2, Registration::where('event_id', $event->id)->count());
    }

    public function test_import_detects_duplicates_within_event(): void
    {
        $event = Event::factory()->create();
        Registration::factory()->create([
            'event_id' => $event->id,
            'email' => 'duplicate@test.com',
        ]);

        $rows = new Collection([
            ['name' => 'Duplicate Person', 'email' => 'duplicate@test.com', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(0, $import->getImportedCount());
        $this->assertNotEmpty($import->getErrors());
    }

    public function test_import_validates_email_format(): void
    {
        $event = Event::factory()->create();
        $rows = new Collection([
            ['name' => 'Bad Email', 'email' => 'not-an-email', 'phone' => '+9779800000001', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(0, $import->getImportedCount());
        $this->assertNotEmpty($import->getErrors());
    }

    public function test_import_requires_at_least_email_or_phone(): void
    {
        $event = Event::factory()->create();
        $rows = new Collection([
            ['name' => 'No Contact', 'email' => '', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(0, $import->getImportedCount());
    }
}
