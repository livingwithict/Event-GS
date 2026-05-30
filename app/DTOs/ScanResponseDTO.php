<?php

namespace App\DTOs;

use App\Models\Registration;

readonly class ScanResponseDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $organization,
        public ?string $designation,
        public bool $hasEntered,
        public bool $lunchUsed,
        public bool $dinnerUsed,
        public ?string $entryTime,
        public ?string $lunchUsedAt,
        public ?string $dinnerUsedAt,
    ) {}

    public static function fromModel(Registration $reg): self
    {
        return new self(
            id: $reg->id,
            name: $reg->name,
            organization: $reg->organization,
            designation: $reg->designation,
            hasEntered: $reg->hasEntered(),
            lunchUsed: $reg->hasUsedMeal('lunch'),
            dinnerUsed: $reg->hasUsedMeal('dinner'),
            entryTime: $reg->entry_time?->toIso8601String(),
            lunchUsedAt: $reg->lunch_used_at?->toIso8601String(),
            dinnerUsedAt: $reg->dinner_used_at?->toIso8601String(),
        );
    }
}
