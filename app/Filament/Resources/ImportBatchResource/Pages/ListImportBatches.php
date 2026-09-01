<?php

namespace App\Filament\Resources\ImportBatchResource\Pages;

use App\Filament\Resources\Concerns\PersistsColumnManagerPerUser;
use App\Filament\Resources\ImportBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListImportBatches extends ListRecords
{
    use PersistsColumnManagerPerUser;

    protected static string $resource = ImportBatchResource::class;
}
