<?php

namespace App\Filament\Resources\PromoCodeResource\Pages;

use App\Filament\Resources\Concerns\PersistsColumnManagerPerUser;
use App\Filament\Resources\PromoCodeResource;
use Filament\Resources\Pages\ListRecords;

class ListPromoCodes extends ListRecords
{
    use PersistsColumnManagerPerUser;

    protected static string $resource = PromoCodeResource::class;
}
