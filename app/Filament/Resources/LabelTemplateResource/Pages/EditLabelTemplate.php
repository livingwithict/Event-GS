<?php

namespace App\Filament\Resources\LabelTemplateResource\Pages;

use App\Filament\Resources\LabelTemplateResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLabelTemplate extends EditRecord
{
    protected static string $resource = LabelTemplateResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Label template updated')
            ->body('The label template has been saved successfully.');
    }
}
