<?php

namespace App\Filament\Resources\ParticipantCategoryResource\Pages;

use App\Filament\Resources\ParticipantCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListParticipantCategories extends ListRecords
{
    protected static string $resource = ParticipantCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
