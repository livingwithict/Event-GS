<?php

namespace App\Filament\Resources\EmailDraftResource\Pages;

use App\Filament\Resources\EmailDraftResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmailDraft extends EditRecord
{
    protected static string $resource = EmailDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
