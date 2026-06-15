<?php

namespace App\Filament\Resources\LabelTemplateResource\Pages;

use App\Filament\Resources\LabelTemplateResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLabelTemplate extends CreateRecord
{
    protected static string $resource = LabelTemplateResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Label template created')
            ->body('The label template has been created successfully.');
    }
}
