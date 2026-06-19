<?php

namespace App\Imports;

use App\Models\Event;
use App\Models\ImportBatch;
use App\Models\ImportError;
use App\Models\ImportStaging;
use App\Models\Registration;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class RegistrationsImport implements ToCollection, WithHeadingRow
{
    private array $errors = [];

    private int $staged = 0;

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

                $rawData = $this->normalizeRow($row);

                ImportStaging::create([
                    'event_id' => $this->event->id,
                    'import_batch_id' => $this->batch?->id,
                    'row_number' => $rowNumber,
                    'raw_data' => $rawData,
                    'name' => $name,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'organization' => trim($row['organization'] ?? '') ?: null,
                    'designation' => trim($row['designation'] ?? '') ?: null,
                    'category_name' => trim($row['category'] ?? '') ?: null,
                    'status' => 'pending',
                ]);

                $this->staged++;
            }

            $this->batch?->markCompleted(
                $rows->count(),
                $this->staged,
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

    public function getStagedCount(): int
    {
        return $this->staged;
    }

    private function normalizeRow($row): array
    {
        if (is_array($row)) {
            return $row;
        }

        if ($row instanceof \ArrayAccess) {
            $data = [];
            foreach ($row as $key => $value) {
                $data[$key] = $value;
            }

            return $data;
        }

        if (method_exists($row, 'toArray')) {
            return $row->toArray();
        }

        return (array) $row;
    }

    private function recordError(int $rowNumber, $row, string $message): void
    {
        $this->errors[] = "Row {$rowNumber}: {$message}";

        if ($this->batch) {
            ImportError::create([
                'import_batch_id' => $this->batch->id,
                'row_number' => $rowNumber,
                'raw_data' => $this->normalizeRow($row),
                'error_message' => $message,
            ]);
        }
    }
}
