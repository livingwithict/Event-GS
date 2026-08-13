<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\ImportBatchResource\Pages;
use App\Models\ImportBatch;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

class ImportBatchResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function getVisibleRoles(): array
    {
        return ['super_admin', 'admin', 'manager'];
    }

    protected static ?string $model = ImportBatch::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['file_name'];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_name')->searchable()->limit(30),
                Tables\Columns\TextColumn::make('event.name')->sortable()->limit(20),
                Tables\Columns\TextColumn::make('importer.name')->label('Imported By')->limit(15),
                Tables\Columns\TextColumn::make('total_rows')->label('Total')->sortable(),
                Tables\Columns\TextColumn::make('success_rows')->label('Success')->sortable()->color('success'),
                Tables\Columns\TextColumn::make('failed_rows')->label('Failed')->sortable()->color('danger'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'failed' => 'danger',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->dateTime('M j, Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No imports yet')
            ->emptyStateDescription('Import registrations from CSV files via the event actions.')
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'failed' => 'Failed']),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make()
                    ->modalHeading('Delete import batch')
                    ->modalDescription('Removes the batch, its staged rows and its error log. Guests already registered from it are kept.')
                    ->disabled(fn (ImportBatch $record) => $record->status === 'processing'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->modalDescription('Removes the batches, their staged rows and error logs. Guests already registered from them are kept.')
                    ->action(function (Collection $records) {
                        $records->reject(fn (ImportBatch $b) => $b->status === 'processing')
                            ->each(fn (ImportBatch $b) => $b->delete());
                    }),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportBatches::route('/'),
            'view' => Pages\ViewImportBatch::route('/{record}'),
        ];
    }
}
