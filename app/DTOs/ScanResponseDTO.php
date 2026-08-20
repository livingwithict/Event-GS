<?php

namespace App\DTOs;

use App\Models\Registration;

readonly class ScanResponseDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $designation,
        public ?string $organization,
        public bool $hasEntered,
        public bool $lunchUsed,
        public bool $dinnerUsed,
        public ?string $entryTime,
        public ?string $lunchUsedAt,
        public ?string $dinnerUsedAt,
        public ?string $categoryName = null,
        public ?string $categoryColor = null,
        public ?string $invitationCategoryName = null,
        public ?string $guestNumber = null,
        public ?string $uniqueCode = null,
        public ?int $eventId = null,
        public array $actions = [],
    ) {}

    public static function fromModel(Registration $reg): self
    {
        return new self(
            id: $reg->id,
            name: $reg->name,
            designation: $reg->designation,
            organization: $reg->organization,
            hasEntered: $reg->hasEntered(),
            lunchUsed: $reg->hasUsedMeal('lunch'),
            dinnerUsed: $reg->hasUsedMeal('dinner'),
            entryTime: $reg->entry_time?->toIso8601String(),
            lunchUsedAt: $reg->lunch_used_at?->toIso8601String(),
            dinnerUsedAt: $reg->dinner_used_at?->toIso8601String(),
            categoryName: $reg->category?->name,
            categoryColor: $reg->category?->badge_color,
            invitationCategoryName: $reg->invitationCategory?->name,
            guestNumber: $reg->guest_number,
            uniqueCode: $reg->unique_code,
            eventId: $reg->event_id,
            actions: $reg->getActionStatuses(),
        );
    }
}
