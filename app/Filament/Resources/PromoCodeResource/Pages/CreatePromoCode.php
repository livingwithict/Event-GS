<?php

namespace App\Filament\Resources\PromoCodeResource\Pages;

use App\Filament\Resources\PromoCodeResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePromoCode extends CreateRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Promo code created')
            ->body('The promo code has been created and is ready to use.');
    }
}
