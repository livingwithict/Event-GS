<?php

namespace App\Filament\Resources\SectorResource\Pages;

use App\Filament\Resources\SectorResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSector extends EditRecord
{
    protected static string $resource = SectorResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sector updated')
            ->body('The sector has been saved successfully.');
    }
}
