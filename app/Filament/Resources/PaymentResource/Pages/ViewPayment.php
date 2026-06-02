<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('verify')
                ->label('Mark as Verified')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->getRecord()->payment_status !== 'success')
                ->action(function () {
                    $payment = $this->getRecord();
                    $payment->markAsSuccess(
                        $payment->gateway_txn_id ?? 'MANUAL-'.$payment->transaction_id,
                        ['manual_verification' => true]
                    );
                    $payment->update([
                        'verified_by' => auth()->id(),
                        'verified_at' => now(),
                    ]);
                }),
        ];
    }
}
