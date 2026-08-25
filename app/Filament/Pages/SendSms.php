<?php

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Enums\Role;
use App\Jobs\SendBulkSMS;
use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SendSms extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Send SMS';

    protected static ?string $title = 'Send SMS';

    protected string $view = 'filament.pages.send-sms';

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        // Invitation Staff send only through the Registration page's bulk action,
        // not this standalone page — same underlying ability, narrower entry point.
        return ($user?->hasAbility(Ability::CommunicationsSend) ?? false)
            && $user?->roleEnum() !== Role::InvitationStaff;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill([
            'event_id' => session('active_event_id') ?: Event::published()->orderBy('start_datetime')->value('id'),
            'approved_only' => true,
            'skip_already_sent' => true,
            'selected_ids' => [],
            'message' => '',
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
                            ->live(),
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
                            ->label('Skip guests who already received this SMS')
                            ->helperText('Safe to re-run — nobody gets it twice.')
                            ->live(),
                    ])->columns(2),

                Section::make('Recipients')
                    ->schema([
                        CheckboxList::make('selected_ids')
                            ->label('Guests to send to')
                            ->options(fn () => $this->recipients()->get()
                                ->filter(fn (Registration $r) => $r->hasMobileNumber())
                                ->mapWithKeys(fn (Registration $r) => [$r->id => $r->displayName().' — '.$r->phone]))
                            ->bulkToggleable()
                            ->searchable()
                            ->helperText('Leave empty to send to everyone matching the filters above.')
                            ->live()
                            ->columns(2),
                    ]),

                Section::make('Message')
                    ->schema([
                        Textarea::make('message')
                            ->label('Text to send')
                            ->rows(4)
                            ->required()
                            ->maxLength(480)
                            ->live(onBlur: true)
                            ->helperText('Plain text only. Guests cannot reply to this number.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Guests reachable by SMS: a mobile number is required, not just a phone. */
    public function recipients(): Builder
    {
        $query = Registration::query()
            ->where('event_id', $this->data['event_id'] ?? 0)
            ->whereNotNull('phone');

        if ($this->data['category_id'] ?? null) {
            $query->where('category_id', $this->data['category_id']);
        }

        if ($this->data['approved_only'] ?? false) {
            $query->where('approval_status', 'approved');
        }

        if ($this->data['skip_already_sent'] ?? false) {
            $query->whereDoesntHave('communications', fn ($q) => $q
                ->where('type', 'sms')
                ->where('status', 'sent'));
        }

        return $query;
    }

    /** Registration IDs this send will actually hit — a manual selection wins, else the filtered query. */
    public function targetIds(): array
    {
        $selectedIds = array_map('intval', $this->data['selected_ids'] ?? []);

        $query = $selectedIds !== []
            ? Registration::query()->whereIn('id', $selectedIds)
            : $this->recipients();

        return $query->get(['id', 'phone'])
            ->filter(fn (Registration $r) => $r->hasMobileNumber())
            ->pluck('id')
            ->all();
    }

    /** @return array{reachable: int, unreachable: int} */
    public function audience(): array
    {
        if (! ($this->data['event_id'] ?? null)) {
            return ['reachable' => 0, 'unreachable' => 0];
        }

        $phones = $this->recipients()->pluck('phone');
        $reachable = $phones->filter(fn ($p) => Registration::isMobileNumber($p))->count();

        $noPhone = Registration::where('event_id', $this->data['event_id'])
            ->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))
            ->count();

        return [
            'reachable' => count($this->targetIds()),
            'unreachable' => ($phones->count() - $reachable) + $noPhone,
        ];
    }

    /** GSM-7 fits 160 characters per part, 153 once a message is split. */
    public function segments(): int
    {
        $length = mb_strlen((string) ($this->data['message'] ?? ''));

        if ($length === 0) {
            return 0;
        }

        return $length <= 160 ? 1 : (int) ceil($length / 153);
    }

    public function isLive(): bool
    {
        return in_array(config('services.sparrow.driver'), ['sociair', 'sparrow'], true)
            && ! empty(config('services.sparrow.token'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')
                ->label(fn () => 'Send to '.$this->audience()['reachable'].' guests')
                ->icon('heroicon-o-paper-airplane')
                ->disabled(fn () => $this->audience()['reachable'] === 0)
                ->requiresConfirmation()
                ->modalHeading('Send this SMS?')
                ->modalDescription(fn () => $this->isLive()
                    ? 'This sends a real text to '.$this->audience()['reachable'].' guests and cannot be undone.'
                    : 'SMS is in log mode — nothing will actually be delivered.')
                ->modalSubmitActionLabel('Send now')
                ->action(function () {
                    $this->form->validate();

                    $ids = $this->targetIds();

                    if (empty($ids)) {
                        Notification::make()->warning()->title('No mobile numbers to send to')->send();

                        return;
                    }

                    foreach (array_chunk($ids, 200) as $chunk) {
                        SendBulkSMS::dispatch($chunk, (int) $this->data['event_id'], $this->data['message']);
                    }

                    Notification::make()
                        ->success()
                        ->title('Queued for '.count($ids).' guests')
                        ->body($this->isLive()
                            ? 'Sending runs in the background. Track it in Communications.'
                            : 'SMS is in log mode, so these are recorded but not delivered.')
                        ->send();
                }),
        ];
    }
}
