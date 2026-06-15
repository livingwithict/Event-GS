<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\RegistrationResource\Pages;
use App\Models\LabelTemplate;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use App\Services\LabelService;
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
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class RegistrationResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function getVisibleRoles(): array
    {
        return ['super_admin', 'event_manager', 'registration_staff', 'finance'];
    }

    protected static ?string $model = Registration::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Attendees';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone', 'guest_number', 'organization', 'event.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->hint(fn ($state) => ($state ? strlen($state) : 0).'/255'),
                        Forms\Components\TextInput::make('guest_number')
                            ->label('Guest Number')
                            ->maxLength(30)
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generated on creation'),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\TextInput::make('organization')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('designation')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('address')
                            ->maxLength(65535)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('website')
                            ->url()
                            ->maxLength(255),
                        Forms\Components\Select::make('gender')
                            ->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'])
                            ->nullable(),
                        Forms\Components\Select::make('meal_preference')
                            ->options(['veg' => 'Vegetarian', 'non-veg' => 'Non-Vegetarian', 'vegan' => 'Vegan', 'halal' => 'Halal'])
                            ->nullable(),
                        Forms\Components\TextInput::make('pan_vat')
                            ->label('PAN/VAT')
                            ->maxLength(50)
                            ->nullable(),
                        Forms\Components\Textarea::make('special_assistance')
                            ->maxLength(500)
                            ->hint(fn ($state) => ($state ? strlen($state) : 0).'/500')
                            ->nullable(),
                        Forms\Components\Textarea::make('notes')
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->nullable(),
                    ])->columns(2)
                    ->columnSpan(1),

                Section::make('Event & Status')
                    ->schema([
                        Forms\Components\Select::make('event_id')
                            ->relationship('event', 'name')
                            ->required()
                            ->searchable()
                            ->live(),
                        Forms\Components\Select::make('category_id')
                            ->relationship('category', 'name')
                            ->label('Category')
                            ->searchable()
                            ->visible(fn (callable $get) => filled($get('event_id')))
                            ->options(fn (callable $get) => ParticipantCategory::where('event_id', $get('event_id'))
                                ->active()
                                ->ordered()
                                ->pluck('name', 'id')),
                        Forms\Components\FileUpload::make('photo_path')
                            ->label('Photo')
                            ->image()
                            ->imagePreview()
                            ->directory('registrations/photos')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->nullable(),
                        Forms\Components\Select::make('registration_source')
                            ->options(['self' => 'Self-Registered', 'csv' => 'CSV Import', 'admin_manual' => 'Admin Manual'])
                            ->default('admin_manual')
                            ->required(),
                        Forms\Components\Select::make('approval_status')
                            ->options(['approved' => 'Approved', 'pending' => 'Pending Approval', 'rejected' => 'Rejected'])
                            ->default('approved')
                            ->visible(fn ($record) => $record?->category?->requires_approval ?? false)
                            ->required(),
                        Forms\Components\Select::make('badge_status')
                            ->options(['not_printed' => 'Not Printed', 'printed' => 'Printed', 'collected' => 'Collected'])
                            ->default('not_printed')
                            ->required(),
                    ])
                    ->columnSpan(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('guest_number')
                    ->label('Guest #')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color(fn ($record) => $record->category?->badge_color ?? 'gray')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('organization')->searchable(),
                Tables\Columns\TextColumn::make('event.name')->sortable(),
                Tables\Columns\TextColumn::make('registration_source')
                    ->label('Source')
                    ->badge()
                    ->colors([
                        'self' => 'success',
                        'csv' => 'info',
                        'admin_manual' => 'gray',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->colors([
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                    ])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->colors([
                        'success' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                    ])
                    ->sortable()
                    ->toggleable(),
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
                Tables\Columns\IconColumn::make('label_printed')
                    ->label('Label')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('badge_status')
                    ->label('Badge')
                    ->badge()
                    ->colors([
                        'not_printed' => 'gray',
                        'printed' => 'warning',
                        'collected' => 'success',
                    ])
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('card_delivered')
                    ->label('Card')
                    ->boolean()
                    ->trueIcon('heroicon-o-credit-card')
                    ->trueColor('success')
                    ->state(fn ($record) => $record->scanLogs()
                        ->whereHas('actionType', fn ($q) => $q->where('action_code', 'CARD_DELIVERY'))
                        ->exists())
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_id')
                    ->relationship('event', 'name')
                    ->label('Event'),
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category'),
                Tables\Filters\SelectFilter::make('registration_source')
                    ->options(['self' => 'Self-Registered', 'csv' => 'CSV Import', 'admin_manual' => 'Admin Manual'])
                    ->label('Source'),
                Tables\Filters\SelectFilter::make('approval_status')
                    ->options(['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected'])
                    ->label('Approval'),
                Tables\Filters\SelectFilter::make('badge_status')
                    ->options(['not_printed' => 'Not Printed', 'printed' => 'Printed', 'collected' => 'Collected'])
                    ->label('Badge'),
                Tables\Filters\TernaryFilter::make('label_printed')
                    ->label('Label Printed')
                    ->trueLabel('Printed')
                    ->falseLabel('Not printed'),
                Tables\Filters\TernaryFilter::make('card_delivered')
                    ->label('Card Delivered')
                    ->trueLabel('Delivered')
                    ->falseLabel('Not delivered')
                    ->queries(
                        true: fn ($query) => $query->whereHas('scanLogs.actionType', fn ($q) => $q->where('action_code', 'CARD_DELIVERY')),
                        false: fn ($query) => $query->whereDoesntHave('scanLogs.actionType', fn ($q) => $q->where('action_code', 'CARD_DELIVERY')),
                    ),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No registrations yet')
            ->emptyStateDescription('Add your first registration or import from CSV.')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('download_ticket')
                    ->label('Ticket')
                    ->icon('heroicon-o-ticket')
                    ->url(fn ($record) => route('ticket.download', $record->qr_hash))
                    ->openUrlInNewTab(),
                Action::make('print_label')
                    ->label('Print Label')
                    ->icon('heroicon-o-tag')
                    ->url(fn ($record) => route('labels.print-single', $record->id))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    BulkAction::make('approve_registrations')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each(fn ($r) => $r->update(['approval_status' => 'approved']));
                        }),
                    BulkAction::make('reject_registrations')
                        ->label('Reject')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each(fn ($r) => $r->update(['approval_status' => 'rejected']));
                        }),
                    BulkAction::make('mark_badge_collected')
                        ->label('Badge Collected')
                        ->icon('heroicon-o-check-badge')
                        ->color('primary')
                        ->action(function (Collection $records) {
                            $records->each(fn ($r) => $r->update(['badge_status' => 'collected']));
                        }),
                    BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            $csv = "Name,Guest #,Category,Email,Phone,Organization,Designation,Entry Time,Lunch Used,Dinner Used\n";
                            foreach ($records as $registration) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $registration->name ?? ''),
                                    str_replace('"', '""', $registration->guest_number ?? ''),
                                    str_replace('"', '""', $registration->category?->name ?? ''),
                                    str_replace('"', '""', $registration->email ?? ''),
                                    str_replace('"', '""', $registration->phone ?? ''),
                                    str_replace('"', '""', $registration->organization ?? ''),
                                    str_replace('"', '""', $registration->designation ?? ''),
                                    $registration->entry_time?->format('Y-m-d H:i:s') ?? '',
                                    $registration->lunch_used_at ? 'Yes' : 'No',
                                    $registration->dinner_used_at ? 'Yes' : 'No'
                                );
                            }

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, 'registrations.csv', ['Content-Type' => 'text/csv']);
                        }),
                    BulkAction::make('print_labels')
                        ->label('Print Labels')
                        ->icon('heroicon-o-tag')
                        ->action(function (Collection $records) {
                            $eventId = $records->first()?->event_id;
                            $template = $eventId ? LabelTemplate::where('event_id', $eventId)->first() : null;

                            if (! $template) {
                                $template = new LabelTemplate([
                                    'template_name' => 'Default',
                                    'width' => 100,
                                    'height' => 60,
                                    'show_qr' => true,
                                    'show_designation' => true,
                                    'show_organization' => true,
                                    'show_category_color' => true,
                                    'font_size_name' => 16,
                                ]);
                            }

                            $service = new LabelService;
                            $pdf = $service->generateLabelPdf($records, $template);
                            $service->markAsPrinted($records);

                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf;
                            }, 'labels.pdf', ['Content-Type' => 'application/pdf']);
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
