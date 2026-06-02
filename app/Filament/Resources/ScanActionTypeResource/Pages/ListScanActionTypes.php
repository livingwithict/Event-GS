<?php

namespace App\Filament\Resources\ScanActionTypeResource\Pages;

use App\Filament\Resources\ScanActionTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScanActionTypes extends ListRecords
{
    protected static string $resource = ScanActionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
