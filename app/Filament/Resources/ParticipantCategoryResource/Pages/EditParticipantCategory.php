<?php

namespace App\Filament\Resources\ParticipantCategoryResource\Pages;

use App\Filament\Resources\ParticipantCategoryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditParticipantCategory extends EditRecord
{
    protected static string $resource = ParticipantCategoryResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Category updated')
            ->body('The participant category has been saved successfully.');
    }
}
