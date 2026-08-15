<?php

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Jobs\SendBulkEmail;
use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SendInvitations extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Send Emails';

    protected static ?string $title = 'Send Emails';

    protected string $view = 'filament.pages.send-invitations';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAbility(Ability::CommunicationsSend) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'event_id' => session('active_event_id') ?: Event::published()->orderBy('start_datetime')->value('id'),
            'email_type' => 'invitation',
            'subject' => 'Invitation',
            'approved_only' => true,
            'skip_already_sent' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Who receives this')
                    ->schema([
                        Select::make('event_id')
                            ->label('Event')
                            ->options(fn () => Event::orderByDesc('start_datetime')->pluck('name', 'id'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->form->fill([...$this->data, 'category_id' => null])),
                        Select::make('category_id')
                            ->label('Category')
                            ->placeholder('All categories')
                            ->options(fn () => ParticipantCategory::where('event_id', $this->data['event_id'] ?? 0)
                                ->orderBy('name')->pluck('name', 'id'))
                            ->live(),
                        Toggle::make('approved_only')
                            ->label('Approved guests only')
                            ->live(),
                        Toggle::make('skip_already_sent')
                            ->label('Skip guests who already received this email')
                            ->helperText('Safe to re-run — nobody gets it twice.')
                            ->live(),
                    ])->columns(2),

                Section::make('What to send')
                    ->schema([
                        Select::make('email_type')
                            ->label('Email')
                            ->options([
                                'invitation' => 'Invitation (attaches the ticket)',
                                'registration_confirmation' => 'Registration confirmation (attaches the ticket)',
                                'event_reminder' => 'Event reminder',
                                'urgent_update' => 'Urgent update',
                                'post_event_thank_you' => 'Post-event thank you',
                            ])
                            ->required()
                            ->live(),
                        TextInput::make('subject')
                            ->label('Subject line')
                            ->required()
                            ->maxLength(150),
                    ])->columns(2),
            ]);
    }

    /** Guests this send would reach, given the current filters. */
    public function recipients(): Builder
    {
        $query = Registration::query()
            ->where('event_id', $this->data['event_id'] ?? 0)
            ->whereNotNull('email');

        if ($this->data['category_id'] ?? null) {
            $query->where('category_id', $this->data['category_id']);
        }

        if ($this->data['approved_only'] ?? false) {
            $query->where('approval_status', 'approved');
        }

        if ($this->data['skip_already_sent'] ?? false) {
            $query->whereDoesntHave('communications', fn ($q) => $q
                ->where('type', 'email')
                ->where('email_type', $this->data['email_type'] ?? 'invitation')
                ->where('status', 'sent'));
        }

        return $query;
    }

    public function recipientCount(): int
    {
        return ($this->data['event_id'] ?? null) ? $this->recipients()->count() : 0;
    }

    /** Guests with no email address are silently unreachable — surface them. */
    public function withoutEmailCount(): int
    {
        if (! ($this->data['event_id'] ?? null)) {
            return 0;
        }

        return Registration::where('event_id', $this->data['event_id'])
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
            ->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label(fn () => 'Send to '.$this->recipientCount().' '.str('guest')->plural($this->recipientCount()))
                ->icon('heroicon-o-paper-airplane')
                ->disabled(fn () => $this->recipientCount() === 0)
                ->requiresConfirmation()
                ->modalHeading('Send these emails?')
                ->modalDescription(fn () => 'This sends real email to '.$this->recipientCount().' guests and cannot be undone.')
                ->modalSubmitActionLabel('Send now')
                ->action(function () {
                    $this->form->validate();

                    $ids = $this->recipients()->pluck('id')->all();

                    if (empty($ids)) {
                        Notification::make()->warning()->title('Nobody to send to')->send();

                        return;
                    }

                    // Chunked so one failure cannot take down the whole run, and
                    // the queue worker stays responsive between batches.
                    foreach (array_chunk($ids, 50) as $chunk) {
                        SendBulkEmail::dispatch(
                            $chunk,
                            (int) $this->data['event_id'],
                            $this->data['subject'],
                            $this->data['email_type'],
                        );
                    }

                    Notification::make()
                        ->success()
                        ->title('Queued for '.count($ids).' guests')
                        ->body('Sending runs in the background at about 2 per second. Watch progress in the log below.')
                        ->send();
                }),
        ];
    }
}
