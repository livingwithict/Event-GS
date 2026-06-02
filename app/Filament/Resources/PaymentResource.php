<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function getVisibleRoles(): array
    {
        return ['super_admin', 'event_manager', 'finance'];
    }

    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
            ->defaultSort('created_at', 'desc')
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
