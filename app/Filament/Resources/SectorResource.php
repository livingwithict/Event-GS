<?php

namespace App\Filament\Resources;

use App\Enums\Ability;
use App\Filament\Clusters\Category;
use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\SectorResource\Pages;
use App\Models\Sector;
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

class SectorResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function requiredAbility(): string
    {
        return Ability::SettingsManage;
    }

    protected static ?string $model = Sector::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $cluster = Category::class;

    protected static ?string $navigationLabel = 'Sector Category';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'event.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sector Details')
                    ->schema([
                        Select::make('event_id')
                            ->relationship('event', 'name')
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->hint(fn ($state) => ($state ? strlen($state) : 0).'/100'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('event.name')->searchable()->sortable(),
                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('Guests')
                    ->sortable(),
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
            ->emptyStateHeading('No sectors yet')
            ->emptyStateDescription('Create sectors like IT, Finance, Healthcare.')
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
            'index' => Pages\ListSectors::route('/'),
            'create' => Pages\CreateSector::route('/create'),
            'edit' => Pages\EditSector::route('/{record}/edit'),
        ];
    }
}
