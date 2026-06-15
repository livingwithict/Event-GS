<?php

namespace App\Imports;

use App\Jobs\SendBulkEmail;
use App\Jobs\SendBulkSMS;
use App\Models\Event;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RegistrationsImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];

    private int $imported = 0;

    private array $importedRegistrationIds = [];

    public function __construct(
        private Event $event,
        private bool $skipDuplicates = true,
        private ?ImportBatch $batch = null,
        private bool $sendNotifications = false,
    ) {}

    public function collection(Collection $rows): void
    {
        $this->batch?->markProcessing();

        try {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                $name = trim($row['name'] ?? '');
                $email = trim($row['email'] ?? '');
                $phone = trim($row['phone'] ?? '');

                if (empty($name)) {
                    $this->recordError($rowNumber, $row, 'Name is required.');

                    continue;
                }

                if (empty($email) && empty($phone)) {
                    $this->recordError($rowNumber, $row, 'At least email or phone is required.');

                    continue;
                }

                if (! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->recordError($rowNumber, $row, 'Invalid email format.');

                    continue;
                }

                if (! empty($phone) && ! preg_match('/^(\+977|0)?9\d{9}$/', $phone)) {
                    $this->recordError($rowNumber, $row, 'Invalid Nepali phone number.');

                    continue;
                }

                $gender = trim($row['gender'] ?? '');
                if (! empty($gender) && ! in_array($gender, ['male', 'female', 'other'])) {
                    $this->recordError($rowNumber, $row, "Invalid gender '{$gender}'. Use male, female, or other.");

                    continue;
                }

                $mealPreference = trim($row['meal_preference'] ?? '');
                if (! empty($mealPreference) && ! in_array($mealPreference, ['veg', 'non-veg', 'vegan', 'halal'])) {
                    $this->recordError($rowNumber, $row, "Invalid meal_preference '{$mealPreference}'. Use veg, non-veg, vegan, or halal.");

                    continue;
                }

                if ($this->skipDuplicates) {
                    $dupeQuery = Registration::where('event_id', $this->event->id);
                    if (! empty($email)) {
                        $dupeQuery->where('email', $email);
                    } elseif (! empty($phone)) {
                        $dupeQuery->where('phone', $phone);
                    }

                    if ($dupeQuery->exists()) {
                        $this->recordError($rowNumber, $row, "Duplicate registration ({$email}{$phone}).");

                        continue;
                    }
                }

                $reg = Registration::create([
                    'event_id' => $this->event->id,
                    'category_id' => $this->resolveCategoryId(trim($row['category'] ?? '')),
                    'registration_source' => 'csv',
                    'name' => $name,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'organization' => trim($row['organization'] ?? '') ?: null,
                    'designation' => trim($row['designation'] ?? '') ?: null,
                    'address' => trim($row['address'] ?? '') ?: null,
                    'website' => trim($row['website'] ?? '') ?: null,
                    'gender' => $gender ?: null,
                    'pan_vat' => trim($row['pan_vat'] ?? '') ?: null,
                    'meal_preference' => $mealPreference ?: null,
                    'special_assistance' => trim($row['special_assistance'] ?? '') ?: null,
                    'notes' => trim($row['notes'] ?? '') ?: null,
                ]);

                $this->imported++;
                $this->importedRegistrationIds[] = $reg->id;
            }

            $this->batch?->markCompleted(
                $rows->count(),
                $this->imported,
                count($this->errors)
            );

            $this->dispatchNotifications();
        } catch (\Throwable $e) {
            $this->batch?->markFailed();

            throw $e;
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getImportedCount(): int
    {
        return $this->imported;
    }

    public function getImportedIds(): array
    {
        return $this->importedRegistrationIds;
    }

    private function dispatchNotifications(): void
    {
        if (! $this->sendNotifications || empty($this->importedRegistrationIds)) {
            return;
        }

        $emailIds = [];
        $smsIds = [];

        foreach ($this->importedRegistrationIds as $regId) {
            $reg = Registration::find($regId);
            if (! $reg) {
                continue;
            }
            if ($reg->email) {
                $emailIds[] = $reg->id;
            }
            if ($reg->phone) {
                $smsIds[] = $reg->id;
            }
        }

        if (! empty($emailIds)) {
            dispatch(new SendBulkEmail(
                $emailIds,
                $this->event->id,
                "Registration Confirmed: {$this->event->name}",
                'registration_confirmation'
            ));
        }

        if (! empty($smsIds)) {
            dispatch(new SendBulkSMS(
                $smsIds,
                $this->event->id,
                "Hello, your registration for {$this->event->name} is confirmed. Check your email for details.",
                'registration_confirmation'
            ));
        }
    }

    private function recordError(int $rowNumber, $row, string $message): void
    {
        $this->errors[] = "Row {$rowNumber}: {$message}";

        if ($this->batch) {
            ImportError::create([
                'import_batch_id' => $this->batch->id,
                'row_number' => $rowNumber,
                'raw_data' => $row->toArray(),
                'error_message' => $message,
            ]);
        }
    }

    private function resolveCategoryId(string $categoryName): ?int
    {
        if (empty($categoryName)) {
            return null;
        }

        return ParticipantCategory::where('event_id', $this->event->id)
            ->where('name', 'like', $categoryName)
            ->value('id');
    }
}
