<?php

namespace App\Http\Resources;

use App\DTOs\ScanResponseDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScanResponseResource extends JsonResource
{
    public function __construct(private ScanResponseDTO $dto)
    {
        parent::__construct($dto);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->dto->id,
            'name' => $this->dto->name,
            'organization' => $this->dto->organization,
            'designation' => $this->dto->designation,
            'has_entered' => $this->dto->hasEntered,
            'lunch_used' => $this->dto->lunchUsed,
            'dinner_used' => $this->dto->dinnerUsed,
            'entry_time' => $this->dto->entryTime,
            'lunch_used_at' => $this->dto->lunchUsedAt,
            'dinner_used_at' => $this->dto->dinnerUsedAt,
        ];
    }
}
