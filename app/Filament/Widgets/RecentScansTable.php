<?php

namespace App\Filament\Widgets;

use App\Models\ScanLog;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentScansTable extends TableWidget
{
    protected static ?int $sort = 6;

    protected static ?string $heading = 'Recent Scans';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ScanLog::with(['participant', 'actionType', 'event', 'scanner'])
                    ->when(session('active_event_id'), fn ($q) => $q->where('event_id', session('active_event_id')))
                    ->latest('scanned_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('participant.name')
                    ->label('Participant')
                    ->searchable()
                    ->limit(25),
                Tables\Columns\TextColumn::make('participant.guest_number')
                    ->label('Guest #')
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('actionType.action_name')
                    ->label('Action')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('event.name')->limit(20),
                Tables\Columns\TextColumn::make('scanner.name')->label('Scanned By')->limit(15),
                Tables\Columns\TextColumn::make('scanned_at')->dateTime('M j, H:i')->sortable(),
            ])
            ->paginated(false);
    }
}
