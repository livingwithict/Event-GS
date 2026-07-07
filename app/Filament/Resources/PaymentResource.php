<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\CommunicationService;
use App\Services\InvoiceService;
use App\Services\Payment\ConnectIPSService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PaymentResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function getVisibleRoles(): array
    {
        return ['super_admin', 'admin', 'finance'];
    }

    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['transaction_id', 'registration.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Details')
                    ->schema([
                        TextInput::make('transaction_id')->maxLength(30)->disabled(),
                        TextInput::make('amount_paisa')
                            ->label('Amount (NPR)')
                            ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                            ->disabled(),
                        Select::make('payment_status')
                            ->options([
                                'pending' => 'Pending',
                                'initiated' => 'Initiated',
                                'success' => 'Success',
                                'failed' => 'Failed',
                                'cancelled' => 'Cancelled',
                                'expired' => 'Expired',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_id')->searchable()->sortable()->fontFamily('mono')->limit(20),
                TextColumn::make('registration.name')->searchable()->sortable()->label('Guest'),
                TextColumn::make('event.name')->searchable()->sortable()->limit(20),
                TextColumn::make('amount_paisa')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => 'NPR '.number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->colors([
                        'pending' => 'warning',
                        'initiated' => 'info',
                        'success' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        'expired' => 'gray',
                        'refunded' => 'purple',
                    ])
                    ->sortable(),
                TextColumn::make('paid_at')->dateTime('M j, Y H:i')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function ($query) {
                $eventId = session('active_event_id');
                if ($eventId) {
                    $query->where('event_id', $eventId);
                }
            })
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No payments yet')
            ->emptyStateDescription('Payments appear here when registrations are processed.')
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'initiated' => 'Initiated',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('revalidate')
                    ->label('Re-validate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (Payment $record) => in_array($record->payment_status, ['pending', 'initiated', 'failed', 'expired', 'cancelled']))
                    ->action(function (Payment $record) {
                        $service = app(ConnectIPSService::class);
                        try {
                            $validate = $service->validatePayment($record);
                            $interpreted = $service->interpretValidationResult($record, $validate);

                            if ($interpreted['outcome'] === 'success') {
                                $detail = $service->getTransactionDetail($record);
                                $record->markAsSuccess(
                                    $interpreted['gateway_txn_id'] ?? $record->transaction_id,
                                    $validate
                                );
                                $record->recordReconciliationDetails($detail);

                                Notification::make()
                                    ->success()
                                    ->title('Payment validated')
                                    ->body('Gateway confirmed success. Reconciliation details saved.')
                                    ->send();
                            } elseif ($interpreted['outcome'] === 'pending') {
                                Notification::make()
                                    ->info()
                                    ->title('Transaction still pending')
                                    ->body('Customer has not completed the payment yet.')
                                    ->send();
                            } else {
                                $record->update([
                                    'payment_status' => 'failed',
                                    'gateway_response' => $validate,
                                ]);
                                Notification::make()
                                    ->danger()
                                    ->title('Payment failed')
                                    ->body($interpreted['status_desc'])
                                    ->send();
                            }
                        } catch (\Throwable $e) {
                            logger()->error('Re-validate failed: '.$e->getMessage());
                            Notification::make()
                                ->danger()
                                ->title('Re-validate error')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('verify_payment')
                    ->label('Verify')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (Payment $record) => in_array($record->payment_status, ['pending', 'initiated']))
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        $record->markAsSuccess(
                            'manual-'.now()->timestamp,
                            ['verified_by' => auth()->id(), 'verified_at' => now()->toDateTimeString(), 'method' => 'manual']
                        );

                        try {
                            if ($record->registration) {
                                $commService = new CommunicationService;
                                $commService->sendPaymentSuccess(
                                    $record->registration,
                                    $record->event,
                                    $record
                                );
                            }
                        } catch (\Throwable $e) {
                            logger()->error('Payment verify notification failed: '.$e->getMessage());
                        }

                        Notification::make()
                            ->success()
                            ->title('Payment verified')
                            ->body('Payment has been marked as successful and confirmation sent.')
                            ->send();
                    }),
                Action::make('mark_invalid')
                    ->label('Mark Invalid')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Payment $record) => in_array($record->payment_status, ['pending', 'initiated']))
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        $record->markAsFailed(['verified_by' => auth()->id(), 'verified_at' => now()->toDateTimeString(), 'method' => 'manual']);

                        Notification::make()
                            ->warning()
                            ->title('Payment marked invalid')
                            ->body('Payment has been marked as failed.')
                            ->send();
                    }),
                Action::make('mark_refunded')
                    ->label('Refund')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Payment $record) => $record->payment_status === 'success')
                    ->requiresConfirmation()
                    ->action(function (Payment $record) {
                        $record->markAsRefunded(auth()->id(), ['method' => 'manual', 'refunded_at' => now()->toDateTimeString()]);
                        Notification::make()
                            ->success()
                            ->title('Payment refunded')
                            ->body('Status set to refunded. Process refund in NCHL merchant portal separately.')
                            ->send();
                    }),
                Action::make('download_invoice')
                    ->label('Invoice')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn ($record) => $record->payment_status === 'success')
                    ->action(function ($record) {
                        $service = new InvoiceService;
                        $pdf = $service->generateInvoicePdf($record);

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf;
                        }, "invoice-{$record->invoice_number}.pdf", ['Content-Type' => 'application/pdf']);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            $csv = "Transaction ID,Guest Name,Guest #,Event,Category,Amount (NPR),Currency,Status,Paid At\n";
                            foreach ($records as $payment) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $payment->transaction_id ?? ''),
                                    str_replace('"', '""', $payment->registration?->name ?? ''),
                                    str_replace('"', '""', $payment->registration?->guest_number ?? ''),
                                    str_replace('"', '""', $payment->event?->name ?? ''),
                                    str_replace('"', '""', $payment->category?->name ?? ''),
                                    number_format($payment->getAmountRupees(), 2),
                                    $payment->currency,
                                    $payment->payment_status,
                                    $payment->paid_at?->format('Y-m-d H:i:s') ?? '',
                                );
                            }

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, 'payments.csv', ['Content-Type' => 'text/csv']);
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
