<?php

namespace App\Filament\Resources\LogisticsResource\Pages;

use App\Filament\Resources\Concerns\PersistsColumnManagerPerUser;
use App\Filament\Resources\LogisticsResource;
use Filament\Resources\Pages\ListRecords;

class ListLogistics extends ListRecords
{
    use PersistsColumnManagerPerUser;

    protected static string $resource = LogisticsResource::class;
}
