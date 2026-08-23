<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommunicationResource\Pages;
use App\Enums\Ability;
use App\Filament\Resources\Concerns\HasRoleBasedVisibility;
use App\Models\Communication;
use App\Services\CommunicationService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class CommunicationResource extends Resource
{
    use HasRoleBasedVisibility;

    protected static function requiredAbility(): string
    {
        return Ability::CommunicationsView;
    }

    protected static ?string $model = Communication::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 1;

    public static function getGloballySearchableAttributes(): array
    {
        return ['registration.name', 'subject'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Communication')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Placeholder::make('registration.name')
                            ->label('Guest')
                            ->content(fn (?Communication $record) => $record?->registration?->displayName() ?? '—'),
                        Placeholder::make('recipient')
                            ->label('Sent to')
                            ->content(fn (?Communication $record) => $record?->type === 'sms'
                                ? ($record?->registration?->phone ?? '—')
                                : ($record?->registration?->email ?? '—')),
                        Placeholder::make('type')
                            ->content(fn (?Communication $record) => ucfirst($record?->type ?? '—')),
                        Placeholder::make('email_type')
                            ->label('Notification type')
                            ->content(fn (?Communication $record) => str_replace('_', ' ', ucwords($record?->email_type ?? '—'))),
                        Placeholder::make('subject')
                            ->content(fn (?Communication $record) => $record?->subject ?: '—'),
                        Placeholder::make('status')
                            ->content(fn (?Communication $record) => ucfirst($record?->status ?? '—')),
                        Placeholder::make('sent_at')
                            ->label('Sent at')
                            ->content(fn (?Communication $record) => $record?->sent_at?->format('M j, Y g:i A') ?? '—'),
                        Placeholder::make('provider_message_id')
                            ->label('Provider message ID')
                            ->content(fn (?Communication $record) => $record?->provider_message_id ?: '—'),
                        Placeholder::make('content')
                            ->label('Message content')
                            ->columnSpanFull()
                            ->content(fn (?Communication $record) => $record?->content ?: '—')
                            ->visible(fn (?Communication $record) => filled($record?->content)),
                        Placeholder::make('metadata')
                            ->label('Details / error')
                            ->columnSpanFull()
                            ->content(fn (?Communication $record) => $record?->metadata
                                ? json_encode($record->metadata, JSON_PRETTY_PRINT)
                                : '—')
                            ->visible(fn (?Communication $record) => filled($record?->metadata)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('registration.name')
                    ->label('Guest')
                    ->description(fn ($record) => $record->registration?->guest_number)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('recipient')
                    ->label('Sent to')
                    ->state(fn ($record) => $record->type === 'sms'
                        ? $record->registration?->phone
                        : $record->registration?->email)
                    ->copyable()
                    ->searchable(query: fn ($query, $search) => $query->whereHas(
                        'registration',
                        fn ($q) => $q->where('email', 'ilike', "%{$search}%")->orWhere('phone', 'ilike', "%{$search}%"),
                    )),
                Tables\Columns\TextColumn::make('type')->badge()->colors(['email' => 'info', 'sms' => 'success']),
                Tables\Columns\TextColumn::make('email_type')
                    ->label('Notification Type')
                    ->badge()
                    ->colors([
                        'registration_confirmation' => 'info',
                        'payment_success' => 'success',
                        'payment_failed' => 'danger',
                        'event_reminder' => 'warning',
                        'post_event_thank_you' => 'info',
                        'invitation' => 'primary',
                        'face_verification' => 'gray',
                        'urgent_update' => 'danger',
                    ])
                    ->formatStateUsing(fn ($state) => str_replace('_', ' ', ucwords($state ?? '')))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('subject')->searchable()->limit(40),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'pending' => 'warning',
                    'sent' => 'success',
                    'failed' => 'danger',
                ]),
                Tables\Columns\TextColumn::make('sent_at')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(['email' => 'Email', 'sms' => 'SMS']),
                Tables\Filters\SelectFilter::make('email_type')
                    ->label('Notification Type')
                    ->options([
                        'registration_confirmation' => 'Registration Confirmation',
                        'payment_success' => 'Payment Success',
                        'payment_failed' => 'Payment Failed',
                        'event_reminder' => 'Event Reminder',
                        'post_event_thank_you' => 'Post-Event Thank You',
                        'invitation' => 'Invitation',
                        'face_verification' => 'Face Verification',
                        'urgent_update' => 'Urgent Update',
                    ]),
                Tables\Filters\SelectFilter::make('status')->options(['pending' => 'Pending', 'sent' => 'Sent', 'failed' => 'Failed']),
                Tables\Filters\SelectFilter::make('invitation_category')
                    ->label('Invitation Category')
                    ->relationship('registration.invitationCategory', 'name'),
                Tables\Filters\Filter::make('sent_at')
                    ->label('Sent Date')
                    ->schema([
                        Forms\Components\DatePicker::make('sent_from'),
                        Forms\Components\DatePicker::make('sent_until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['sent_from'] ?? null, fn ($q, $date) => $q->whereDate('sent_at', '>=', $date))
                            ->when($data['sent_until'] ?? null, fn ($q, $date) => $q->whereDate('sent_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data) {
                        $indicators = [];

                        if ($data['sent_from'] ?? null) {
                            $indicators[] = 'Sent from '.\Carbon\Carbon::parse($data['sent_from'])->format('M j, Y');
                        }

                        if ($data['sent_until'] ?? null) {
                            $indicators[] = 'Sent until '.\Carbon\Carbon::parse($data['sent_until'])->format('M j, Y');
                        }

                        return $indicators;
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(20)
            ->paginationPageOptions([10, 20, 50])
            ->emptyStateHeading('No communications yet')
            ->emptyStateDescription('Communications are created when invites or reminders are sent.')
            ->recordActions([
                ViewAction::make()->modalWidth('2xl'),
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn ($record) => $record->status === 'failed')
                    ->action(function ($record) {
                        $commService = app(CommunicationService::class);
                        $reg = $record->registration;

                        if ($record->type === 'email' && $reg && $reg->email) {
                            $event = $reg->event;
                            $commService->sendEmail($reg, $event, $record->subject ?? 'Invitation', $record->email_type ?? 'invitation');
                        } elseif ($record->type === 'sms' && $reg && $reg->phone) {
                            $commService->sendSms($reg, $record->content ?? '', $record->email_type);
                        }
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('export')
                        ->label('Export CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function (Collection $records) {
                            $csv = "Guest Name,Email,Phone,Event,Type,Notification Type,Subject,Status,Sent At,Error\n";
                            foreach ($records as $comm) {
                                $csv .= sprintf(
                                    "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                                    str_replace('"', '""', $comm->registration?->name ?? ''),
                                    str_replace('"', '""', $comm->registration?->email ?? ''),
                                    str_replace('"', '""', $comm->registration?->phone ?? ''),
                                    str_replace('"', '""', $comm->registration?->event?->name ?? ''),
                                    $comm->type,
                                    str_replace('_', ' ', $comm->email_type ?? ''),
                                    str_replace('"', '""', $comm->subject ?? ''),
                                    $comm->status,
                                    $comm->sent_at?->format('Y-m-d H:i:s') ?? '',
                                    str_replace('"', '""', $comm->metadata['error'] ?? ''),
                                );
                            }

                            return response()->streamDownload(function () use ($csv) {
                                echo $csv;
                            }, 'communications.csv', ['Content-Type' => 'text/csv']);
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunications::route('/'),
        ];
    }
}
