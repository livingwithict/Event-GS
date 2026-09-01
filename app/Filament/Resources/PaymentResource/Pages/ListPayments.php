<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\Concerns\PersistsColumnManagerPerUser;
use App\Filament\Resources\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    use PersistsColumnManagerPerUser;

    protected static string $resource = PaymentResource::class;
}
