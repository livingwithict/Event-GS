<?php

namespace App\Filament\Resources;

use App\Enums\Ability;
use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Filament\Resources\RegistrationResource\Pages;
use App\Models\LabelTemplate;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use App\Services\CommunicationService;
use App\Services\LabelService;
use App\Services\QRCodeService;
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
use App\Jobs\SendBulkEmail;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class RegistrationResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function requiredAbility(): string
    {
        return Ability::GuestsView;
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
                        Forms\Components\Select::make('salutation')
                            ->label('Title')
                            ->options(array_combine(Registration::SALUTATIONS, Registration::SALUTATIONS))
                            ->searchable()
                            ->native(false)
                            ->placeholder('None'),
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
                            ->maxLength(50)
                            ->helperText('Mobile, landline or extension — any format.'),
                        Forms\Components\TextInput::make('designation')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('organization')
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
                            ->directory('registrations/photos')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->nullable(),
                        Forms\Components\Select::make('registration_source')
                            ->options(['self' => 'Self-Registered', 'csv' => 'CSV Import', 'admin_manual' => 'Admin Manual'])
                            ->default('admin_manual')
                            ->required(),
                        Forms\Components\Select::make('approval_status')
                            ->options(['approved' => 'Approved', 'pending' => 'Pending Approval', 'waitlisted' => 'Waitlisted', 'rejected' => 'Rejected'])
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
                Tables\Columns\TextColumn::make('name')
                    ->formatStateUsing(fn (Registration $record) => $record->displayName())
                    ->searchable()
                    ->sortable(),
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
                Tables\Columns\TextColumn::make('designation')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('organization')->searchable(),
                Tables\Columns\TextColumn::make('address')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(),
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
                        'waitlisted' => 'info',
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
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category'),
                Tables\Filters\SelectFilter::make('registration_source')
                    ->options(['self' => 'Self-Registered', 'csv' => 'CSV Import', 'admin_manual' => 'Admin Manual'])
                    ->label('Source'),
                Tables\Filters\SelectFilter::make('approval_status')
                    ->options(['approved' => 'Approved', 'pending' => 'Pending', 'waitlisted' => 'Waitlisted', 'rejected' => 'Rejected'])
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
                ViewAction::make()
                    ->modalHeading('Registration QR Pass')
                    ->modalContent(fn (Registration $record) => view('filament.registration-qr-preview', [
                        'registration' => $record->load(['event', 'category']),
                        'qrSvg' => app(QRCodeService::class)->generateSvg($record),
                    ])),
                EditAction::make()
                    ->visible(fn () => Auth::user()?->hasAbility(Ability::GuestsEdit)),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Registration $record) => $record->approval_status === 'pending'
                        && (Auth::user()?->hasAbility(Ability::GuestsApprove) ?? false))
                    ->action(function (Registration $record) {
                        $record->update(['approval_status' => 'approved']);
                        try {
                            $commService = new CommunicationService;
                            $commService->sendRegistrationConfirmation($record, $record->event);
                        } catch (\Throwable $e) {
                            logger()->error('Failed to send approval confirmation: '.$e->getMessage());
                        }
                        Notification::make()
                            ->success()
                            ->title('Registration approved')
                            ->body("{$record->name} has been approved and notification sent.")
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Registration $record) => $record->approval_status === 'pending'
                        && (Auth::user()?->hasAbility(Ability::GuestsApprove) ?? false))
                    ->requiresConfirmation()
                    ->action(function (Registration $record) {
                        $record->update(['approval_status' => 'rejected']);
                        Notification::make()
                            ->warning()
                            ->title('Registration rejected')
                            ->body("{$record->name} has been rejected.")
                            ->send();
                    }),
                Action::make('promote_from_waitlist')
                    ->label('Promote')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('primary')
                    ->visible(fn (Registration $record) => $record->approval_status === 'waitlisted'
                        && (Auth::user()?->hasAbility(Ability::GuestsApprove) ?? false))
                    ->action(function (Registration $record) {
                        $record->update(['approval_status' => 'approved']);
                        try {
                            $commService = new CommunicationService;
                            $commService->sendRegistrationConfirmation($record, $record->event);
                        } catch (\Throwable $e) {
                            logger()->error('Failed to send waitlist promotion notification: '.$e->getMessage());
                        }
                        Notification::make()
                            ->success()
                            ->title('Promoted from waitlist')
                            ->body("{$record->name} has been promoted and notification sent.")
                            ->send();
                    }),
                Action::make('see_status')
                    ->label('See Status')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn (Registration $record): string => route('checkin.verify', $record->guest_number))
                    ->openUrlInNewTab(),
                Action::make('view_ticket')
                    ->label('Ticket')
                    ->icon('heroicon-o-ticket')
                    ->visible(fn () => Auth::user()?->hasAbility(Ability::TicketsView))
                    ->url(fn ($record) => route('ticket.download', $record->qr_hash))
                    ->openUrlInNewTab(),
                Action::make('download_qr')
                    ->label('Download QR')
                    ->icon('heroicon-o-qr-code')
                    ->visible(fn () => Auth::user()?->hasAbility(Ability::TicketsView))
                    ->url(fn (Registration $record): string => route('ticket.qr-print', $record->qr_hash))
                    ->openUrlInNewTab(),
                Action::make('preview_label')
                    ->label('Preview Label')
                    ->icon('heroicon-o-eye')
                    ->visible(fn () => Auth::user()?->hasAbility(Ability::LabelsPrint))
                    ->url(fn ($record) => route('labels.print-single', ['registration' => $record->id, 'preview' => 1]))
                    ->openUrlInNewTab(),
                Action::make('print_label')
                    ->label('Print Label')
                    ->icon('heroicon-o-printer')
                    ->visible(fn () => Auth::user()?->hasAbility(Ability::LabelsPrint))
                    ->url(fn ($record) => route('labels.print-now', ['registrations' => $record->id]))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->visible(fn () => Auth::user()?->hasAbility(Ability::EventsManage)),
                ForceDeleteAction::make()
                    ->visible(fn () => Auth::user()?->hasAbility(Ability::EventsManage)),
                RestoreAction::make()
                    ->visible(fn () => Auth::user()?->hasAbility(Ability::EventsManage)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasAbility(Ability::EventsManage)),
                    ForceDeleteBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasAbility(Ability::EventsManage)),
                    RestoreBulkAction::make()
                        ->visible(fn () => Auth::user()?->hasAbility(Ability::EventsManage)),
                    BulkAction::make('approve_registrations')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn () => Auth::user()?->hasAbility(Ability::GuestsApprove))
                        ->action(function (Collection $records) {
                            $records->each(fn ($r) => $r->update(['approval_status' => 'approved']));
                        }),
                    BulkAction::make('reject_registrations')
                        ->label('Reject')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->visible(fn () => Auth::user()?->hasAbility(Ability::GuestsApprove))
                        ->action(function (Collection $records) {
                            $records->each(fn ($r) => $r->update(['approval_status' => 'rejected']));
                        }),
                    BulkAction::make('mark_badge_collected')
                        ->label('Badge Collected')
                        ->icon('heroicon-o-check-badge')
                        ->color('primary')
                        ->visible(fn () => Auth::user()?->hasAbility(Ability::GuestsEdit))
                        ->action(function (Collection $records) {
                            $records->each(fn ($r) => $r->update(['badge_status' => 'collected']));
                        }),
                    BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            $csv = "Title,Name,Guest #,Category,Email,Phone,Designation,Organization,Address,Entry Time,Lunch Used,Dinner Used\n";
                            foreach ($records as $registration) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $registration->salutation ?? ''),
                                    str_replace('"', '""', $registration->name ?? ''),
                                    str_replace('"', '""', $registration->guest_number ?? ''),
                                    str_replace('"', '""', $registration->category?->name ?? ''),
                                    str_replace('"', '""', $registration->email ?? ''),
                                    str_replace('"', '""', $registration->phone ?? ''),
                                    str_replace('"', '""', $registration->designation ?? ''),
                                    str_replace('"', '""', $registration->organization ?? ''),
                                    str_replace('"', '""', str_replace(["\r", "\n"], ' ', $registration->address ?? '')),
                                    $registration->entry_time?->format('Y-m-d H:i:s') ?? '',
                                    $registration->lunch_used_at ? 'Yes' : 'No',
                                    $registration->dinner_used_at ? 'Yes' : 'No'
                                );
                            }

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, 'registrations.csv', ['Content-Type' => 'text/csv']);
                        }),
                    BulkAction::make('send_invitation')
                        ->label('Send Invitation Email')
                        ->icon('heroicon-o-paper-airplane')
                        ->visible(fn () => Auth::user()?->hasAbility(Ability::CommunicationsSend))
                        ->requiresConfirmation()
                        ->modalHeading('Send invitation emails?')
                        ->modalDescription('Each guest gets their ticket attached. This sends real email and cannot be undone.')
                        ->modalSubmitActionLabel('Send now')
                        ->schema([
                            \Filament\Forms\Components\TextInput::make('subject')
                                ->label('Subject line')
                                ->default('Your invitation')
                                ->required()
                                ->maxLength(150),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $byEvent = $records->filter(fn ($r) => filled($r->email))->groupBy('event_id');

                            if ($byEvent->isEmpty()) {
                                Notification::make()->warning()
                                    ->title('No email addresses')
                                    ->body('None of the selected guests have an email address.')
                                    ->send();

                                return;
                            }

                            $queued = 0;

                            foreach ($byEvent as $eventId => $group) {
                                foreach ($group->pluck('id')->chunk(50) as $chunk) {
                                    SendBulkEmail::dispatch($chunk->all(), (int) $eventId, $data['subject'], 'invitation');
                                }
                                $queued += $group->count();
                            }

                            Notification::make()->success()
                                ->title("Queued for {$queued} guests")
                                ->body('Sending runs in the background. Track it in Communications.')
                                ->send();
                        }),
                    BulkAction::make('print_labels')
                        ->label('Print Labels')
                        ->icon('heroicon-o-printer')
                        ->visible(fn () => Auth::user()?->hasAbility(Ability::LabelsPrint))
                        ->action(fn (Collection $records) => redirect()->route('labels.print-now', [
                            'registrations' => $records->pluck('id')->implode(','),
                        ])),
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
