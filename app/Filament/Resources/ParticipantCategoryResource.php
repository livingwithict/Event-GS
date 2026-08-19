<?php

namespace App\Filament\Resources;

use App\Enums\Ability;
use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\ParticipantCategoryResource\Pages;
use App\Models\ParticipantCategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ParticipantCategoryResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function requiredAbility(): string
    {
        return Ability::SettingsManage;
    }

    protected static ?string $model = ParticipantCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Events';

    protected static ?int $navigationSort = 2;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'event.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Category Details')
                    ->schema([
                        Select::make('event_id')
                            ->relationship('event', 'name')
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->hint(fn ($state) => ($state ? strlen($state) : 0).'/100'),
                        Textarea::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        ColorPicker::make('badge_color')
                            ->label('Badge Color'),
                    ])
                    ->columnSpan(1),

                Section::make('Settings')
                    ->schema([
                        Toggle::make('is_paid')
                            ->label('Paid Category')
                            ->live()
                            ->default(false),
                        TextInput::make('price')
                            ->numeric()
                            ->prefix('NPR')
                            ->minValue(0)
                            ->visible(fn (callable $get) => $get('is_paid')),
                        TextInput::make('early_bird_price')
                            ->numeric()
                            ->prefix('NPR')
                            ->minValue(0)
                            ->label('Early Bird Price')
                            ->helperText('Discounted price before the deadline')
                            ->visible(fn (callable $get) => $get('is_paid')),
                        DateTimePicker::make('early_bird_until')
                            ->label('Early Bird Deadline')
                            ->seconds(false)
                            ->helperText('After this date, the regular price applies')
                            ->visible(fn (callable $get) => $get('is_paid')),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        Toggle::make('requires_approval')
                            ->label('Requires Approval')
                            ->helperText('Registrations in this category need admin approval before confirmation')
                            ->default(false),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        Select::make('label_template_id')
                            ->label('Label Template')
                            ->relationship('labelTemplate', 'template_name')
                            ->nullable()
                            ->helperText('Template used when printing labels for this category'),
                        CheckboxList::make('qr_access_permissions')
                            ->label('QR Access Permissions')
                            ->helperText('Limit which scan actions this category can use. Leave empty to allow all.')
                            ->options([
                                'CHECKIN' => 'Check-In',
                                'LUNCH' => 'Lunch',
                                'DINNER' => 'Dinner',
                                'CARD_DELIVERY' => 'Card Delivery',
                                'BADGE_COLLECT' => 'Badge Collection',
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('event.name')->searchable()->sortable(),
                IconColumn::make('is_paid')
                    ->label('Paid')
                    ->boolean()
                    ->trueIcon('heroicon-o-currency-dollar')
                    ->trueColor('success'),
                TextColumn::make('price')
                    ->money('NPR')
                    ->sortable()
                    ->toggleable(),
                ColorColumn::make('badge_color')
                    ->label('Color'),
                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('Registrations')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('requires_approval')
                    ->label('Approval')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No categories yet')
            ->emptyStateDescription('Create participant categories like VIP, General, Staff.')
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
                Tables\Filters\TernaryFilter::make('is_paid')
                    ->label('Paid'),
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
            'index' => Pages\ListParticipantCategories::route('/'),
            'create' => Pages\CreateParticipantCategory::route('/create'),
            'edit' => Pages\EditParticipantCategory::route('/{record}/edit'),
        ];
    }
}
