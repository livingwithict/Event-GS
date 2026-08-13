<?php

namespace App\Filament\Resources\ImportBatchResource\Pages;

use App\Filament\Resources\ImportBatchResource;
use App\Models\ImportError;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ViewImportBatch extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ImportBatchResource::class;

    protected string $view = 'filament.resources.import-batch-resource.pages.view-import-batch';

    /** Every row the import rejected, with the values that caused it. */
    public function table(Table $table): Table
    {
        return $table
            ->query(ImportError::query()->where('import_batch_id', $this->getRecord()->id))
            ->columns([
                TextColumn::make('row_number')->label('Row')->sortable(),
                TextColumn::make('error_message')
                    ->label('Why it failed')
                    ->color('danger')
                    ->wrap(),
                TextColumn::make('raw_data')
                    ->label('Name')
                    ->formatStateUsing(fn ($state) => data_get($state, 'name') ?: '—'),
                TextColumn::make('email')
                    ->label('Email')
                    ->state(fn (ImportError $record) => data_get($record->raw_data, 'email') ?: '—')
                    ->wrap(),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->state(fn (ImportError $record) => data_get($record->raw_data, 'phone') ?: '—'),
            ])
            ->defaultSort('row_number')
            ->paginated([25, 50, 100])
            ->emptyStateHeading('No failed rows');
    }
}
