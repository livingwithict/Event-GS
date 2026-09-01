<?php

namespace App\Filament\Resources\SectorResource\Pages;

use App\Filament\Resources\Concerns\PersistsColumnManagerPerUser;
use App\Filament\Resources\SectorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSectors extends ListRecords
{
    use PersistsColumnManagerPerUser;

    protected static string $resource = SectorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
