<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\ScanActionTypeResource\Pages;
use App\Models\ScanActionType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ScanActionTypeResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static ?string $model = ScanActionType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|UnitEnum|null $navigationGroup = 'Events';

    protected static ?int $navigationSort = 3;

    public static function getGloballySearchableAttributes(): array
    {
        return ['action_name', 'action_code'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Action Details')
                    ->schema([
                        Select::make('event_id')
                            ->relationship('event', 'name')
                            ->required()
                            ->searchable(),
                        TextInput::make('action_name')
                            ->label('Action Name')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('action_code')
                            ->label('Action Code')
                            ->required()
                            ->maxLength(50)
                            ->extraInputAttributes(['style' => 'text-transform: uppercase'])
                            ->rules(['regex:/^[A-Z0-9_]+$/']),
                        Select::make('column_mapping')
                            ->label('Column Mapping')
                            ->options([
                                'entry_time' => 'Entry Time',
                                'lunch_used_at' => 'Lunch Used At',
                                'dinner_used_at' => 'Dinner Used At',
                            ])
                            ->nullable()
                            ->helperText('Maps to a registration column for legacy compatibility. Leave empty for custom actions.'),
                    ])
                    ->columnSpan(1),

                Section::make('Settings')
                    ->schema([
                        Toggle::make('allow_multiple')
                            ->label('Allow Multiple Scans')
                            ->default(false),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action_name')->searchable()->sortable(),
                TextColumn::make('action_code')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('day_number')
                    ->label('Day')
                    ->state(fn ($record) => preg_match('/^DAY(\d+)_/', $record->action_code, $m) ? 'Day '.$m[1] : '-')
                    ->badge()
                    ->color('info')
                    ->toggleable(),
                TextColumn::make('event.name')->searchable()->sortable(),
                TextColumn::make('column_mapping')
                    ->label('Mapped Column')
                    ->toggleable(),
                IconColumn::make('allow_multiple')
                    ->label('Multiple')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No scan actions yet')
            ->emptyStateDescription('Define scan actions like Check-in, Lunch, Dinner for your events.')
            ->modifyQueryUsing(function ($query) {
                $eventId = session('active_event_id');
                if ($eventId) {
                    $query->where('event_id', $eventId);
                }
            })
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\SelectFilter::make('day_specific')
                    ->label('Action Type')
                    ->options(['day' => 'Day-Specific', 'general' => 'General'])
                    ->query(fn ($state, $query) => match ($state['value'] ?? null) {
                        'day' => $query->where('action_code', 'LIKE', 'DAY%'),
                        'general' => $query->where('action_code', 'NOT LIKE', 'DAY%'),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScanActionTypes::route('/'),
            'create' => Pages\CreateScanActionType::route('/create'),
            'edit' => Pages\EditScanActionType::route('/{record}/edit'),
        ];
    }
}
