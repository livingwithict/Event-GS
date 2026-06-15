<?php

namespace App\Filament\Resources\ParticipantCategoryResource\Pages;

use App\Filament\Resources\ParticipantCategoryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateParticipantCategory extends CreateRecord
{
    protected static string $resource = ParticipantCategoryResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Category created')
            ->body('The participant category has been created successfully.');
    }
}
