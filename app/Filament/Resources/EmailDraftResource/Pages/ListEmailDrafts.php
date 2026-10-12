<?php

namespace App\Filament\Resources\EmailDraftResource\Pages;

use App\Filament\Resources\EmailDraftResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEmailDrafts extends ListRecords
{
    protected static string $resource = EmailDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
