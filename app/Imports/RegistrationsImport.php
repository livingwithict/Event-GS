<?php

namespace App\Imports;

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

    public function __construct(
        private Event $event,
        private bool $skipDuplicates = true,
        private ?ImportBatch $batch = null,
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

                Registration::create([
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
                ]);

                $this->imported++;
            }

            $this->batch?->markCompleted(
                $rows->count(),
                $this->imported,
                count($this->errors)
            );
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
