<?php

namespace App\Filament\Resources;

use App\Enums\Ability;
use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\PromoCodeResource\Pages;
use App\Models\PromoCode;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class PromoCodeResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function requiredAbility(): string
    {
        return Ability::SettingsManage;
    }

    protected static ?string $model = PromoCode::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'Events';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Promo Codes';

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'event.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Promo Code Details')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', 'name')
                            ->required(),
                        Forms\Components\TextInput::make('code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->hint(fn ($state) => ($state ? strlen($state) : 0).'/50'),
                        Forms\Components\Select::make('discount_type')
                            ->options([
                                'percentage' => 'Percentage (%)',
                                'fixed' => 'Fixed Amount (NPR)',
                            ])
                            ->required()
                            ->default('percentage'),
                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->hint(fn (callable $get) => $get('discount_type') === 'percentage' ? 'Enter percentage (e.g. 10 for 10%)' : 'Enter amount in NPR'),
                    ])
                    ->columnSpan(1),

                Section::make('Usage & Validity')
                    ->schema([
                        Forms\Components\TextInput::make('max_uses')
                            ->label('Maximum Uses')
                            ->numeric()
                            ->minValue(1)
                            ->helperText('Leave empty for unlimited uses'),
                        Forms\Components\DateTimePicker::make('valid_from')
                            ->label('Valid From')
                            ->seconds(false),
                        Forms\Components\DateTimePicker::make('valid_until')
                            ->label('Valid Until')
                            ->seconds(false)
                            ->after('valid_from'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('event.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('discount_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'percentage' ? 'Percentage' : 'Fixed'),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Discount')
                    ->formatStateUsing(function ($record): string {
                        if ($record->discount_type === 'percentage') {
                            return $record->discount_value.'%';
                        }

                        return 'NPR '.number_format($record->discount_value, 2);
                    }),
                Tables\Columns\TextColumn::make('usage')
                    ->label('Used')
                    ->state(fn (PromoCode $record): string => $record->used_count.($record->max_uses ? ' / '.$record->max_uses : ''))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Valid From')
                    ->date('M j, Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Valid Until')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No promo codes yet')
            ->emptyStateDescription('Create promo codes to offer discounts on paid categories.')
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
                Tables\Filters\SelectFilter::make('discount_type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                    ])
                    ->label('Discount Type'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
