<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegistrationResource\Pages;
use App\Models\Registration;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('event_id')
                    ->relationship('event', 'name')
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
                Forms\Components\TextInput::make('phone')->tel()->maxLength(20),
                Forms\Components\TextInput::make('organization')->maxLength(255),
                Forms\Components\TextInput::make('designation')->maxLength(255),
                Forms\Components\Textarea::make('address')->maxLength(65535),
                Forms\Components\TextInput::make('website')->url()->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('organization')->searchable(),
                Tables\Columns\TextColumn::make('event.name')->sortable(),
                Tables\Columns\IconColumn::make('entry_time')
                    ->label('Entered')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\IconColumn::make('lunch_used_at')
                    ->label('Lunch')
                    ->boolean(),
                Tables\Columns\IconColumn::make('dinner_used_at')
                    ->label('Dinner')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            $csv = "Name,Email,Phone,Organization,Designation,Entry Time,Lunch Used,Dinner Used\n";
                            foreach ($records as $registration) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $registration->name ?? ''),
                                    str_replace('"', '""', $registration->email ?? ''),
                                    str_replace('"', '""', $registration->phone ?? ''),
                                    str_replace('"', '""', $registration->organization ?? ''),
                                    str_replace('"', '""', $registration->designation ?? ''),
                                    $registration->entry_time?->format('Y-m-d H:i:s') ?? '',
                                    $registration->lunch_used_at ? 'Yes' : 'No',
                                    $registration->dinner_used_at ? 'Yes' : 'No'
                                );
                            }

                            return response($csv, 200, [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="registrations.csv"',
                            ]);
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrations::route('/'),
            'create' => Pages\CreateRegistration::route('/create'),
            'edit' => Pages\EditRegistration::route('/{record}/edit'),
        ];
    }
}
