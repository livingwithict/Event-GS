<?php

namespace App\Filament\Widgets;

use App\Models\Registration;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentRegistrationsTable extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Recent Registrations';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Registration::with(['event', 'category'])->latest()->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->limit(25),
                Tables\Columns\TextColumn::make('guest_number')
                    ->label('Guest #')
                    ->fontFamily('mono')
                    ->copyable(),
                Tables\Columns\TextColumn::make('event.name')->limit(20),
                Tables\Columns\TextColumn::make('category.name')
                    ->badge()
                    ->color(fn ($record) => $record->category?->badge_color ?? 'gray'),
                Tables\Columns\TextColumn::make('registration_source')
                    ->label('Source')
                    ->badge()
                    ->colors([
                        'self' => 'success',
                        'csv' => 'info',
                        'admin_manual' => 'gray',
                    ]),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->colors([
                        'success' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime('M j, H:i')->sortable(),
            ])
            ->paginated(false);
    }
}
