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

                // Contact details are taken as typed: guest lists carry landlines,
                // extensions, two addresses in one cell. Losing the guest over a
                // format rule costs more than an address we cannot mail.

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

                // Colleagues share an organisation email/phone, so a duplicate is
                // the same name on the same contact — not the contact alone.
                // "Existing" means either an already-registered guest, or another
                // row still pending review from this or an earlier CSV upload —
                // otherwise two identical rows in the same file would both pass.
                if ($this->skipDuplicates && (! empty($email) || ! empty($phone))) {
                    $matchContact = function ($query) use ($email, $phone) {
                        if (! empty($email)) {
                            $query->where('email', $email);
                        } elseif (! empty($phone)) {
                            $query->where('phone', $phone);
                        }
                    };

                    $isDuplicate = Registration::where('event_id', $this->event->id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->where($matchContact)
                        ->exists();

                    if (! $isDuplicate) {
                        $isDuplicate = ImportStaging::where('event_id', $this->event->id)
                            ->pending()
                            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                            ->where($matchContact)
                            ->exists();
                    }

                    if ($isDuplicate) {
                        $this->recordError($rowNumber, $row, "{$name} is already registered ({$email}{$phone}).");

                        continue;
                    }
                }

                $rawData = $this->normalizeRow($row);

                ImportStaging::create([
                    'event_id' => $this->event->id,
                    'import_batch_id' => $this->batch?->id,
                    'row_number' => $rowNumber,
                    'raw_data' => $rawData,
                    'salutation' => trim($row['salutation'] ?? '') ?: null,
                    'name' => $name,
                    'email' => $email ?: null,
                    'phone' => $phone ?: null,
                    'organization' => trim($row['organization'] ?? '') ?: null,
                    'designation' => trim($row['designation'] ?? '') ?: null,
                    'address' => trim($row['address'] ?? '') ?: null,
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
