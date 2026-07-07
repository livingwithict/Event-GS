<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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

    public function infolist(Schema $schema): Schema
    {
        $payment = $this->getRecord();

        return $schema
            ->components([
                Section::make('Transaction')
                    ->schema([
                        TextEntry::make('transaction_id')->label('Transaction ID')->copyable(),
                        TextEntry::make('payment_status')
                            ->badge()
                            ->colors([
                                'pending' => 'warning',
                                'initiated' => 'info',
                                'success' => 'success',
                                'failed' => 'danger',
                                'cancelled' => 'gray',
                                'expired' => 'gray',
                                'refunded' => 'purple',
                            ]),
                        TextEntry::make('amount_paisa')
                            ->label('Amount')
                            ->formatStateUsing(fn ($state) => 'NPR '.number_format($state / 100, 2)),
                        TextEntry::make('currency'),
                        TextEntry::make('subtotal')->formatStateUsing(fn ($state) => $state !== null ? 'NPR '.number_format($state, 2) : '—'),
                        TextEntry::make('tax_amount')->formatStateUsing(fn ($state) => $state !== null ? 'NPR '.number_format($state, 2) : '—'),
                        TextEntry::make('paid_at')->dateTime(),
                        TextEntry::make('expires_at')->dateTime(),
                    ])->columns(2),

                Section::make('Gateway Reconciliation')
                    ->schema([
                        TextEntry::make('gateway_txn_id')->label('NCHL Txn ID')->copyable()->placeholder('—'),
                        TextEntry::make('batch_id')->label('NCHL Batch ID')->copyable()->placeholder('—'),
                        TextEntry::make('debit_bank_code')->label('Debit Bank')->placeholder('—'),
                        TextEntry::make('charge_amount_paisa')
                            ->label('Gateway Charge')
                            ->formatStateUsing(fn ($state) => $state !== null ? 'NPR '.number_format($state / 100, 2) : '—'),
                        TextEntry::make('credit_status')->label('Credit Status')->placeholder('—'),
                        TextEntry::make('invoice_number')->copyable()->placeholder('—'),
                    ])->columns(2),

                Section::make('Guest')
                    ->schema([
                        TextEntry::make('registration.name')->label('Name'),
                        TextEntry::make('registration.guest_number')->label('Guest #')->copyable(),
                        TextEntry::make('registration.email')->placeholder('—'),
                        TextEntry::make('registration.phone')->placeholder('—'),
                    ])->columns(2),

                Section::make('Raw Gateway Response')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('gateway_response_json')
                            ->label('')
                            ->state(fn () => json_encode($payment->gateway_response ?? [], JSON_PRETTY_PRINT))
                            ->html()
                            ->fontFamily('mono'),
                    ]),
            ]);
    }
}
