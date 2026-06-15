<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\LabelTemplateResource\Pages;
use App\Models\LabelTemplate;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class LabelTemplateResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static ?string $model = LabelTemplate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function getGloballySearchableAttributes(): array
    {
        return ['template_name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Template Details')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\TextInput::make('template_name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('width')
                                    ->label('Width (mm)')
                                    ->numeric()
                                    ->default(100)
                                    ->minValue(30)
                                    ->maxValue(200)
                                    ->required(),
                                Forms\Components\TextInput::make('height')
                                    ->label('Height (mm)')
                                    ->numeric()
                                    ->default(60)
                                    ->minValue(20)
                                    ->maxValue(100)
                                    ->required(),
                            ]),
                        Forms\Components\TextInput::make('font_size_name')
                            ->label('Name Font Size (px)')
                            ->numeric()
                            ->default(16)
                            ->minValue(10)
                            ->maxValue(28)
                            ->required(),
                    ])
                    ->columnSpan(1),

                Section::make('Label Display')
                    ->schema([
                        Forms\Components\Fieldset::make('Show on Label')
                            ->schema([
                                Forms\Components\Toggle::make('show_qr')
                                    ->label('QR Code')
                                    ->default(true),
                                Forms\Components\Toggle::make('show_designation')
                                    ->label('Designation')
                                    ->default(true),
                                Forms\Components\Toggle::make('show_organization')
                                    ->label('Organization')
                                    ->default(true),
                                Forms\Components\Toggle::make('show_category_color')
                                    ->label('Category Color Strip')
                                    ->default(true),
                            ]),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('template_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('event.name')->sortable(),
                Tables\Columns\TextColumn::make('width')->label('Width (mm)'),
                Tables\Columns\TextColumn::make('height')->label('Height (mm)'),
                Tables\Columns\IconColumn::make('show_qr')->label('QR')->boolean(),
                Tables\Columns\IconColumn::make('show_designation')->label('Desig.')->boolean(),
                Tables\Columns\IconColumn::make('show_organization')->label('Org.')->boolean(),
                Tables\Columns\IconColumn::make('show_category_color')->label('Color')->boolean(),
            ])
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No label templates yet')
            ->emptyStateDescription('Create label templates for badge printing.')
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLabelTemplates::route('/'),
            'create' => Pages\CreateLabelTemplate::route('/create'),
            'edit' => Pages\EditLabelTemplate::route('/{record}/edit'),
        ];
    }
}
