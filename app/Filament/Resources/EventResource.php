<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
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
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $state, Set $set) {
                                $set('slug', Str::slug($state));
                            }),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        DatePicker::make('event_date')
                            ->required(),
                        Textarea::make('venue')
                            ->maxLength(65535),
                    ])->columns(2),

                Section::make('Settings')
                    ->schema([
                        CheckboxList::make('meal_types')
                            ->options(['lunch' => 'Lunch', 'dinner' => 'Dinner'])
                            ->default(['lunch', 'dinner'])
                            ->required(),
                        TextInput::make('max_capacity')
                            ->numeric()
                            ->minValue(1),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('event_date')->date()->sortable(),
                TextColumn::make('venue')->limit(30)->searchable(),
                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('Registrations')
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->label('Year')
                    ->options(fn () => Event::selectRaw('EXTRACT(YEAR FROM event_date) as year')
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->map(fn ($y) => (string) $y)
                        ->toArray(),
                    )
                    ->query(fn (Tables\Filters\SelectFilter $filter, $query) => $filter->getState()['value']
                        ? $query->whereYear('event_date', $filter->getState()['value'])
                        : $query
                    ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
                BulkAction::make('meal_usage_report')
                    ->label('Meal Usage Report')
                    ->icon('heroicon-o-cake')
                    ->action(function (Collection $records) {
                        $csv = "Name,Organization,Designation,Lunch Used,Lunch Time,Dinner Used,Dinner Time\n";
                        foreach ($records as $event) {
                            foreach ($event->registrations()->whereNotNull('entry_time')->orderBy('name')->get() as $reg) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $reg->name),
                                    str_replace('"', '""', $reg->organization ?? ''),
                                    str_replace('"', '""', $reg->designation ?? ''),
                                    $reg->lunch_used_at ? 'Yes' : 'No',
                                    $reg->lunch_used_at?->format('Y-m-d H:i:s') ?? '',
                                    $reg->dinner_used_at ? 'Yes' : 'No',
                                    $reg->dinner_used_at?->format('Y-m-d H:i:s') ?? '',
                                );
                            }
                        }

                        return response($csv, 200, [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="meal-usage.csv"',
                        ]);
                    }),
                BulkAction::make('summary_report')
                    ->label('Event Summary Report')
                    ->icon('heroicon-o-document-chart-bar')
                    ->action(function (Collection $records) {
                        $csv = "Event,Date,Venue,Registrations,Entries,No-Shows,Lunch Used,Dinner Used,Duplicates\n";
                        foreach ($records as $event) {
                            $stats = $event->getStats();
                            $total = $stats['total_registrations'];
                            $noShows = $total - $stats['total_entries'];
                            $duplicates = Activity::where('subject_type', Registration::class)
                                ->where('description', 'like', 'Duplicate%')
                                ->whereHasMorph('subject', Registration::class, function ($query) use ($event) {
                                    $query->where('event_id', $event->id);
                                })
                                ->count();

                            $csv .= sprintf(
                                "\"%s\",\"%s\",\"%s\",%d,%d,%d,%d,%d,%d\n",
                                str_replace('"', '""', $event->name),
                                $event->event_date?->format('Y-m-d') ?? '',
                                str_replace('"', '""', $event->venue ?? ''),
                                $total,
                                $stats['total_entries'],
                                $noShows,
                                $stats['lunch_used'],
                                $stats['dinner_used'],
                                $duplicates,
                            );
                        }

                        return response($csv, 200, [
                            'Content-Type' => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="event-summaries.csv"',
                        ]);
                    }),
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
                            $csv = "Name,Slug,Date,Venue,Meal Types,Max Capacity\n";
                            foreach ($records as $event) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $event->name ?? ''),
                                    str_replace('"', '""', $event->slug ?? ''),
                                    $event->event_date?->format('Y-m-d') ?? '',
                                    str_replace('"', '""', $event->venue ?? ''),
                                    implode(', ', $event->meal_types ?? []),
                                    $event->max_capacity ?? ''
                                );
                            }

                            return response($csv, 200, [
                                'Content-Type' => 'text/csv',
                                'Content-Disposition' => 'attachment; filename="events.csv"',
                            ]);
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
