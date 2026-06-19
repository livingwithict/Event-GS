<?php

namespace App\Filament\Pages;

use App\Imports\RegistrationsImport;
use App\Models\Event;
use App\Models\ImportBatch;
use App\Models\ImportStaging;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use App\Services\CommunicationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use UnitEnum;

class ImportPreview extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string|UnitEnum|null $navigationGroup = 'Attendees';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Import Guests';

    protected static ?string $title = 'Import & Register Guests';

    protected string $view = 'filament.pages.import-preview';

    public ?int $eventId = null;

    public function mount(): void
    {
        $this->eventId = session('active_event_id');
    }

    public function getTitle(): string
    {
        return 'Import & Register Guests';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload_csv')
                ->label('Upload CSV / XLSX')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->form([
                    FileUpload::make('file')
                        ->label('CSV or XLSX File')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $event = Event::findOrFail($this->eventId);

                    $batch = ImportBatch::create([
                        'event_id' => $event->id,
                        'imported_by' => Auth::id(),
                        'file_name' => basename($data['file']),
                        'status' => 'pending',
                    ]);

                    $import = new RegistrationsImport($event, batch: $batch);
                    Excel::import($import, storage_path('app/public/'.$data['file']));

                    Notification::make()
                        ->title('Import Complete')
                        ->body("Staged {$import->getStagedCount()} contacts for registration. ".count($import->getErrors()).' errors.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImportStaging::where('event_id', $this->eventId)
                    ->with('registration')
                    ->latest()
            )
            ->columns([
                TextColumn::make('row_number')->label('#')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('organization')->searchable()->limit(20),
                TextColumn::make('category_name')->label('Category')->badge()->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'pending' => 'warning',
                        'registered' => 'success',
                        'error' => 'danger',
                    ])
                    ->sortable(),
                IconColumn::make('registration_id')
                    ->label('Registered')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success'),
                TextColumn::make('created_at')->dateTime('M j, H:i')->sortable(),
            ])
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No imported contacts')
            ->emptyStateDescription('Upload a CSV or XLSX file to begin importing guests.')
            ->recordActions([
                Action::make('register')
                    ->label('Register')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ImportStaging $record) => $record->status === 'pending')
                    ->action(fn (ImportStaging $record) => $this->registerContact($record)),
                Action::make('skip')
                    ->label('Skip')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (ImportStaging $record) => $record->status === 'pending')
                    ->action(function (ImportStaging $record) {
                        $record->update(['status' => 'skipped']);
                        Notification::make()->success()->title('Contact skipped')->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('register_all')
                    ->label('Register Selected')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status === 'pending') {
                                $this->registerContact($record);
                                $count++;
                            }
                        }
                        Notification::make()
                            ->success()
                            ->title("Registered {$count} contacts")
                            ->body('QR codes generated and notifications queued.')
                            ->send();
                    }),
            ]);
    }

    private function registerContact(ImportStaging $staging): void
    {
        if ($staging->status !== 'pending') {
            return;
        }

        $event = $staging->event;
        $raw = $staging->raw_data;
        $categoryName = trim($raw['category'] ?? '');

        $categoryId = null;
        if (! empty($categoryName)) {
            $categoryId = ParticipantCategory::where('event_id', $event->id)
                ->where('name', 'like', $categoryName)
                ->value('id');
        }

        $reg = Registration::create([
            'event_id' => $event->id,
            'category_id' => $categoryId,
            'registration_source' => 'csv',
            'approval_status' => 'approved',
            'name' => trim($raw['name'] ?? ''),
            'email' => trim($raw['email'] ?? '') ?: null,
            'phone' => trim($raw['phone'] ?? '') ?: null,
            'organization' => trim($raw['organization'] ?? '') ?: null,
            'designation' => trim($raw['designation'] ?? '') ?: null,
            'address' => trim($raw['address'] ?? '') ?: null,
            'website' => trim($raw['website'] ?? '') ?: null,
            'gender' => trim($raw['gender'] ?? '') ?: null,
            'pan_vat' => trim($raw['pan_vat'] ?? '') ?: null,
            'meal_preference' => trim($raw['meal_preference'] ?? '') ?: null,
            'special_assistance' => trim($raw['special_assistance'] ?? '') ?: null,
            'notes' => trim($raw['notes'] ?? '') ?: null,
            'consented_at' => now(),
        ]);

        try {
            $commService = new CommunicationService;
            $commService->sendRegistrationConfirmation($reg, $event);
        } catch (\Throwable $e) {
            logger()->error('Import registration notification failed: '.$e->getMessage());
        }

        $staging->update([
            'status' => 'registered',
            'registration_id' => $reg->id,
        ]);
    }
}
