<?php

namespace App\Filament\Resources\PromoCodeResource\Pages;

use App\Filament\Resources\PromoCodeResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPromoCode extends EditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Promo code updated')
            ->body('The promo code has been updated successfully.');
    }
}
