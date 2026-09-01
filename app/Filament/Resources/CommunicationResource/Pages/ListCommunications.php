<?php

namespace App\Filament\Resources\CommunicationResource\Pages;

use App\Filament\Resources\CommunicationResource;
use App\Filament\Resources\Concerns\PersistsColumnManagerPerUser;
use Filament\Resources\Pages\ListRecords;

class ListCommunications extends ListRecords
{
    use PersistsColumnManagerPerUser;

    protected static string $resource = CommunicationResource::class;
}
