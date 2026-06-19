<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use App\Models\Registration;
use Filament\Actions\Action;
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
use UnitEnum;

class EventResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function getVisibleRoles(): array
    {
        return ['super_admin', 'admin', 'manager', 'finance'];
    }

    protected static ?string $model = Event::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Events';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'venue'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                // --- LEFT COLUMN ---
                Section::make('Event Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $state, Set $set) {
                                $set('slug', Str::slug($state));
                            })
                            ->hint(fn ($state) => ($state ? strlen($state) : 0).'/255'),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->prefix('/events/')
                            ->unique(ignoreRecord: true),
                        RichEditor::make('description')
                            ->maxLength(65535)
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'bold', 'italic', 'underline',
                                'h1', 'h2', 'h3',
                                'bulletList', 'orderedList',
                                'link', 'blockquote',
                            ]),
                        TextInput::make('contact_info')
                            ->label('Contact Info / Main Organizer')
                            ->maxLength(255),
                    ])->columns(2)
                    ->columnSpan(1),

                Section::make('Schedule')
                    ->schema([
                        DateTimePicker::make('start_datetime')
                            ->label('Start Date & Time')
                            ->required()
                            ->seconds(false),
                        DateTimePicker::make('end_datetime')
                            ->label('End Date & Time')
                            ->seconds(false)
                            ->after('start_datetime'),
                        DateTimePicker::make('registration_open_at')
                            ->label('Registration Opens')
                            ->seconds(false),
                        DateTimePicker::make('registration_close_at')
                            ->label('Registration Closes')
                            ->seconds(false)
                            ->after('registration_open_at'),
                    ])->columns(2)
                    ->columnSpan(1),

                Section::make('Venue & Capacity')
                    ->schema([
                        TextInput::make('venue')
                            ->maxLength(65535),
                        CheckboxList::make('meal_types')
                            ->options(['lunch' => 'Lunch', 'dinner' => 'Dinner'])
                            ->default(['lunch', 'dinner'])
                            ->required(),
                        TextInput::make('max_capacity')
                            ->numeric()
                            ->minValue(1),
                    ])->columns(3)
                    ->columnSpan(1),

                // --- RIGHT COLUMN ---
                Section::make('Status')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'closed' => 'Closed',
                                'archived' => 'Archived',
                            ])
                            ->required()
                            ->default('draft'),
                    ])
                    ->columnSpan(1),

                Section::make('Images')
                    ->schema([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('events/logos')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp']),
                        FileUpload::make('banner_path')
                            ->label('Banner')
                            ->image()
                            ->disk('public')
                            ->directory('events/banners')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp']),
                    ])->columns(2)
                    ->columnSpan(1)
                    ->collapsible()
                    ->collapsed(),

                Section::make('Toggle Settings')
                    ->description('Enable or disable features for this event')
                    ->schema([
                        Toggle::make('settings.enable_self_registration')
                            ->label('Self-Registration')
                            ->default(true),
                        Toggle::make('settings.enable_payment')
                            ->label('Payment')
                            ->default(false),
                        Toggle::make('settings.enable_csv_import')
                            ->label('CSV Import')
                            ->default(true),
                        Toggle::make('settings.enable_checkin')
                            ->label('Check-In Tracking')
                            ->default(true),
                        Toggle::make('settings.enable_lunch')
                            ->label('Lunch Tracking')
                            ->default(true),
                        Toggle::make('settings.enable_dinner')
                            ->label('Dinner Tracking')
                            ->default(true),
                        Toggle::make('settings.enable_card_delivery')
                            ->label('Card Delivery Tracking')
                            ->default(false),
                        Toggle::make('settings.enable_label_printing')
                            ->label('Label Printing')
                            ->default(false),
                        Toggle::make('settings.enable_notifications')
                            ->label('Email/SMS Notifications')
                            ->default(true),
                        Toggle::make('settings.auto_generate_day_actions')
                            ->label('Auto-Generate Day Actions')
                            ->default(true)
                            ->helperText('For multi-day events, automatically create day-specific scan actions.'),
                        TextInput::make('settings.tax_rate')
                            ->label('Tax Rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(50)
                            ->default(0)
                            ->helperText('Tax/VAT percentage applied to paid registrations.'),
                        Toggle::make('settings.enable_waitlist')
                            ->label('Enable Waitlist')
                            ->default(false)
                            ->helperText('When at capacity, allow registrations to join a waitlist instead of blocking.'),
                    ])->columns(3)
                    ->columnSpan(1)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('start_datetime')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->label('Starts'),
                TextColumn::make('end_datetime')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->label('Ends')
                    ->toggleable(),
                TextColumn::make('duration')
                    ->label('Duration')
                    ->state(fn ($record) => $record->isMultiDay()
                        ? $record->getTotalDays().' days'
                        : '1 day'
                    )
                    ->tooltip(fn ($record) => $record->isMultiDay()
                        ? $record->start_datetime?->format('M j').' - '.$record->end_datetime?->format('M j, Y')
                        : $record->start_datetime?->format('M j, Y')
                    )
                    ->badge()
                    ->color(fn ($record) => $record->isMultiDay() ? 'info' : 'gray'),
                TextColumn::make('venue')->limit(30)->searchable()->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'draft' => 'gray',
                        'published' => 'success',
                        'closed' => 'warning',
                        'archived' => 'danger',
                    ])
                    ->sortable(),
                TextColumn::make('registrations_count')
                    ->counts('registrations')
                    ->label('Registrations')
                    ->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_datetime', 'desc')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No events yet')
            ->emptyStateDescription('Create your first event to get started.')
            ->filters([
                Tables\Filters\SelectFilter::make('year')
                    ->label('Year')
                    ->options(fn () => Event::selectRaw('EXTRACT(YEAR FROM start_datetime) as year')
                        ->distinct()
                        ->orderByDesc('year')
                        ->pluck('year', 'year')
                        ->map(fn ($y) => (string) $y)
                        ->toArray(),
                    )
                    ->query(fn (Tables\Filters\SelectFilter $filter, $query) => $filter->getState()['value']
                        ? $query->whereYear('start_datetime', $filter->getState()['value'])
                        : $query
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'closed' => 'Closed',
                        'archived' => 'Archived',
                    ]),
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

                        return response()->streamDownload(function () use ($csv) {
                            echo $csv;
                        }, 'meal-usage.csv', ['Content-Type' => 'text/csv']);
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
                                $event->start_datetime?->format('Y-m-d') ?? '',
                                str_replace('"', '""', $event->venue ?? ''),
                                $total,
                                $stats['total_entries'],
                                $noShows,
                                $stats['lunch_used'],
                                $stats['dinner_used'],
                                $duplicates,
                            );
                        }

                        return response()->streamDownload(function () use ($csv) {
                            echo $csv;
                        }, 'event-summaries.csv', ['Content-Type' => 'text/csv']);
                    }),
                Action::make('download_pdf_summary')
                    ->label('PDF Summary')
                    ->icon('heroicon-o-document-text')
                    ->url(fn ($record) => route('reports.pdf-summary', $record))
                    ->openUrlInNewTab(),
                Action::make('export_payments')
                    ->label('Payments')
                    ->icon('heroicon-o-banknotes')
                    ->url(fn ($record) => route('reports.payments', $record))
                    ->openUrlInNewTab(),
                Action::make('export_scanner_activity')
                    ->label('Scanner Activity')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn ($record) => route('reports.scanner-activity', $record))
                    ->openUrlInNewTab(),
                Action::make('export_category_summary')
                    ->label('Category Summary')
                    ->icon('heroicon-o-chart-bar')
                    ->url(fn ($record) => route('reports.category-summary', $record))
                    ->openUrlInNewTab(),
                Action::make('export_card_delivery')
                    ->label('Card Delivery')
                    ->icon('heroicon-o-credit-card')
                    ->url(fn ($record) => route('reports.card-delivery', $record))
                    ->openUrlInNewTab(),
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
                                    $event->start_datetime?->format('Y-m-d') ?? '',
                                    str_replace('"', '""', $event->venue ?? ''),
                                    implode(', ', $event->meal_types ?? []),
                                    $event->max_capacity ?? ''
                                );
                            }

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, 'events.csv', ['Content-Type' => 'text/csv']);
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
