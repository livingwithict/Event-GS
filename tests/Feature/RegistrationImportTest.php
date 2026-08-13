<?php

namespace Tests\Feature;

use App\Imports\RegistrationsImport;
use App\Models\Event;
use App\Models\ImportStaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RegistrationImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_stages_valid_rows(): void
    {
        $event = Event::factory()->create();
        $rows = new Collection([
            ['name' => 'John Doe', 'email' => 'john@test.com', 'phone' => '+9779800000001', 'organization' => 'ICT', 'designation' => '', 'address' => '', 'website' => ''],
            ['name' => 'Jane Smith', 'email' => 'jane@test.com', 'phone' => '+9779800000002', 'organization' => 'Tech Corp', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(2, $import->getStagedCount());
        $this->assertEmpty($import->getErrors());
        $this->assertEquals(2, ImportStaging::where('event_id', $event->id)->count());
        $this->assertEquals('pending', ImportStaging::first()->status);
    }

    public function test_import_detects_duplicates_within_event(): void
    {
        $event = Event::factory()->create();

        $import = new RegistrationsImport($event, skipDuplicates: false);

        $rows = new Collection([
            ['name' => 'Person 1', 'email' => 'same@test.com', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
            ['name' => 'Person 2', 'email' => 'same@test.com', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import->collection($rows);

        $this->assertEquals(2, $import->getStagedCount());
    }

    public function test_import_keeps_contact_details_exactly_as_supplied(): void
    {
        $event = Event::factory()->create();
        $rows = new Collection([
            // Real guest lists carry these: a landline, an extension, two addresses
            // in one cell. None of them should cost us the guest.
            ['name' => 'Landline Guest', 'email' => 'info@nicasiabank.com', 'phone' => '01-5444477', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
            ['name' => 'Two Emails', 'email' => 'a@example.com, b@example.com', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
            ['name' => 'Partial Email', 'email' => '@nrb.org.np', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(3, $import->getStagedCount());
        $this->assertEmpty($import->getErrors());
        $this->assertDatabaseHas('import_staging', ['name' => 'Landline Guest', 'phone' => '01-5444477']);
    }

    public function test_import_still_requires_a_name_and_some_contact(): void
    {
        $event = Event::factory()->create();
        $rows = new Collection([
            ['name' => '', 'email' => 'someone@example.com', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(0, $import->getStagedCount());
        $this->assertNotEmpty($import->getErrors());
    }

    public function test_colleagues_may_share_one_organisation_email_and_phone(): void
    {
        $event = Event::factory()->create();

        foreach (['Sita Rai', 'Bikash Thapa'] as $name) {
            \App\Models\Registration::factory()->create([
                'event_id' => $event->id,
                'name' => $name,
                'email' => 'info@acme.com.np',
                'phone' => '9801234567',
            ]);
        }

        $this->assertSame(2, \App\Models\Registration::where('event_id', $event->id)->count());
    }

    public function test_import_still_rejects_the_same_person_twice(): void
    {
        $event = Event::factory()->create();
        \App\Models\Registration::factory()->create([
            'event_id' => $event->id,
            'name' => 'Sita Rai',
            'email' => 'info@acme.com.np',
        ]);

        $rows = new Collection([
            ['name' => 'Sita Rai', 'email' => 'info@acme.com.np', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
            ['name' => 'Bikash Thapa', 'email' => 'info@acme.com.np', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(1, $import->getStagedCount());
        $this->assertCount(1, $import->getErrors());
    }

    public function test_a_deleted_guest_does_not_reserve_their_email_or_phone(): void
    {
        $event = Event::factory()->create();

        $first = \App\Models\Registration::factory()->create([
            'event_id' => $event->id,
            'email' => 'repeat@example.com',
            'phone' => '9800000001',
        ]);
        $first->delete();

        $replacement = \App\Models\Registration::factory()->create([
            'event_id' => $event->id,
            'email' => 'repeat@example.com',
            'phone' => '9800000001',
        ]);

        $this->assertNotSame($first->id, $replacement->id);
        $this->assertSame(1, \App\Models\Registration::where('event_id', $event->id)->count());
    }

    public function test_import_requires_at_least_email_or_phone(): void
    {
        $event = Event::factory()->create();
        $rows = new Collection([
            ['name' => 'No Contact', 'email' => '', 'phone' => '', 'organization' => '', 'designation' => '', 'address' => '', 'website' => ''],
        ]);

        $import = new RegistrationsImport($event);
        $import->collection($rows);

        $this->assertEquals(0, $import->getStagedCount());
    }
}
