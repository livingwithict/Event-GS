<?php

namespace App\Filament\Resources\ScanActionTypeResource\Pages;

use App\Filament\Resources\ScanActionTypeResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateScanActionType extends CreateRecord
{
    protected static string $resource = ScanActionTypeResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Scan action created')
            ->body('The scan action has been created successfully.');
    }
}
