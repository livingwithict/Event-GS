<?php

namespace App\Filament\Resources\ScanActionTypeResource\Pages;

use App\Filament\Resources\ScanActionTypeResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditScanActionType extends EditRecord
{
    protected static string $resource = ScanActionTypeResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Scan action updated')
            ->body('The scan action has been saved successfully.');
    }
}
