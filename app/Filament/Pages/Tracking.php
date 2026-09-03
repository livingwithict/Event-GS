<?php

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Models\DeliveryMean;
use App\Models\Event;
use App\Models\Registration;
use App\Services\QRCodeService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Tracking extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Logistics';

    protected static ?string $navigationLabel = 'Tracking';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Delivery Tracking';

    protected string $view = 'filament.pages.tracking';

    public ?int $eventId = null;

    public int|string|null $deliveryMeanId = null;

    public string $code = '';

    /** Last scan outcome: ['status' => ok|error, 'title' => .., 'lines' => [..]] */
    public ?array $result = null;

    /** Ten most recent scans this session, newest first. */
    public array $recent = [];

    /** Live name search — separate from the exact-match code field. */
    public string $nameQuery = '';

    /** Guests matching nameQuery: ['id', 'label', 'code', 'delivery_mean']. */
    public array $nameResults = [];

    public int|string|null $lookupMeanId = null;

    public ?string $lookupMeanDescription = null;

    /** Guests currently assigned to lookupMeanId: ['id', 'label', 'code']. */
    public array $lookupMeanGuests = [];

    public string $newMeanName = '';

    public ?string $newMeanDescription = null;

    public ?int $editingMeanId = null;

    public string $editMeanName = '';

    public ?string $editMeanDescription = null;

    public ?int $confirmingDeleteMeanId = null;

    public int $confirmingDeleteMeanGuestCount = 0;

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAbility(Ability::DeliveryManage) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->eventId = session('active_event_id') ?: Event::published()->orderBy('start_datetime')->value('id');
        $this->deliveryMeanId = $this->deliveryMeans()->keys()->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageDeliveryMeans')
                ->label('Manage Delivery Means')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->action('openManageMeans'),
        ];
    }

    /** Opens the manage-means modal fresh, never mid-edit from last time. */
    public function openManageMeans(): void
    {
        $this->resetManageState();
        $this->dispatch('open-modal', id: 'manage-delivery-means');
    }

    public function updatedEventId(): void
    {
        session(['active_event_id' => $this->eventId]);
        $this->deliveryMeanId = $this->deliveryMeans()->keys()->first();
        $this->result = null;
        $this->nameQuery = '';
        $this->nameResults = [];
        $this->recent = [];
        $this->lookupMeanId = null;
        $this->lookupMeanDescription = null;
        $this->lookupMeanGuests = [];
    }

    public function events()
    {
        $user = Auth::user();

        return ($user?->accessibleEvents() ?? Event::query())
            ->orderByDesc('events.start_datetime')
            ->pluck('events.name', 'events.id');
    }

    /** @return Collection<int, string> id => name, scoped to the active event */
    public function deliveryMeans()
    {
        if (! $this->eventId) {
            return collect();
        }

        return DeliveryMean::where('event_id', $this->eventId)->orderBy('name')->pluck('name', 'id');
    }

    /** Scan submitted: barcode wedge types the code and sends Enter. */
    public function scan(): void
    {
        $code = strtoupper(trim($this->code));
        $this->code = '';
        $this->result = null;

        if ($code === '') {
            return;
        }

        $mean = $this->deliveryMeanId ? DeliveryMean::find($this->deliveryMeanId) : null;

        if (! $mean || $mean->event_id !== $this->eventId) {
            $this->fail('No delivery means selected', ['Pick a delivery means first.']);

            return;
        }

        $reg = app(QRCodeService::class)->resolve($code);

        if (! $reg) {
            $this->fail('Not found', ["No guest matches \"{$code}\"."]);

            return;
        }

        if ($reg->event_id !== $this->eventId) {
            $this->fail($reg->displayName(), ['This guest belongs to a different event.'], $reg);

            return;
        }

        $reg->update(['delivery_mean_id' => $mean->id]);

        $this->push('ok', $reg->displayName(), ["Assigned to {$mean->name}."], $reg, $mean->name);
    }

    /** Fires on every keystroke (wire:model.live) — cheap LIKE search. */
    public function updatedNameQuery(): void
    {
        $query = trim($this->nameQuery);

        if (mb_strlen($query) < 2) {
            $this->nameResults = [];

            return;
        }

        $this->nameResults = Registration::where('event_id', $this->eventId)
            ->whereRaw('UPPER(name) LIKE ?', ['%'.mb_strtoupper($query).'%'])
            ->orderBy('name')
            ->limit(8)
            ->with('deliveryMean')
            ->get()
            ->map(fn (Registration $r) => [
                'id' => $r->id,
                'label' => $r->displayName(),
                'code' => $r->guest_number,
                'delivery_mean' => $r->deliveryMean?->name,
            ])->all();
    }

    public function updatedLookupMeanId(): void
    {
        $mean = $this->lookupMeanId ? DeliveryMean::find($this->lookupMeanId) : null;

        if (! $mean) {
            $this->lookupMeanDescription = null;
            $this->lookupMeanGuests = [];

            return;
        }

        $this->lookupMeanDescription = $mean->description;
        $this->lookupMeanGuests = $mean->registrations()
            ->orderBy('name')
            ->get()
            ->map(fn (Registration $r) => ['id' => $r->id, 'label' => $r->displayName(), 'code' => $r->guest_number])
            ->all();
    }

    private function resetManageState(): void
    {
        $this->cancelEditMean();
        $this->cancelDeleteMean();
        $this->newMeanName = '';
        $this->newMeanDescription = null;
    }

    public function createMean(): void
    {
        $name = trim($this->newMeanName);

        if ($name === '') {
            return;
        }

        DeliveryMean::create([
            'event_id' => $this->eventId,
            'name' => $name,
            'description' => $this->newMeanDescription ?: null,
        ]);

        $this->newMeanName = '';
        $this->newMeanDescription = null;
    }

    public function startEditMean(int $id): void
    {
        $mean = DeliveryMean::find($id);

        if (! $mean) {
            return;
        }

        $this->editingMeanId = $mean->id;
        $this->editMeanName = $mean->name;
        $this->editMeanDescription = $mean->description;
    }

    public function saveEditMean(): void
    {
        $mean = DeliveryMean::find($this->editingMeanId);

        if (! $mean) {
            return;
        }

        $mean->update([
            'name' => trim($this->editMeanName),
            'description' => $this->editMeanDescription ?: null,
        ]);

        $this->cancelEditMean();
    }

    public function cancelEditMean(): void
    {
        $this->editingMeanId = null;
        $this->editMeanName = '';
        $this->editMeanDescription = null;
    }

    /** Staff clicked delete: report how many guests will lose their assignment before actually deleting. */
    public function requestDeleteMean(int $id): void
    {
        $this->confirmingDeleteMeanId = $id;
        $this->confirmingDeleteMeanGuestCount = Registration::where('delivery_mean_id', $id)->count();
    }

    public function confirmDeleteMean(): void
    {
        if (! $this->confirmingDeleteMeanId) {
            return;
        }

        DeliveryMean::destroy($this->confirmingDeleteMeanId);

        if ($this->lookupMeanId === $this->confirmingDeleteMeanId) {
            $this->lookupMeanId = null;
            $this->lookupMeanDescription = null;
            $this->lookupMeanGuests = [];
        }

        $this->cancelDeleteMean();
    }

    public function cancelDeleteMean(): void
    {
        $this->confirmingDeleteMeanId = null;
        $this->confirmingDeleteMeanGuestCount = 0;
    }

    private function fail(string $title, array $lines, ?Registration $reg = null): void
    {
        $this->push('error', $title, $lines, $reg);
    }

    private function push(string $status, string $title, array $lines, ?Registration $reg = null, ?string $meanName = null): void
    {
        $this->result = [
            'status' => $status,
            'title' => $title,
            'lines' => array_values($lines),
        ];

        array_unshift($this->recent, [
            'status' => $status,
            'name' => $reg?->displayName() ?? $title,
            'code' => $reg?->guest_number ?? '',
            'mean' => $meanName ?? '',
            'at' => now()->format('H:i:s'),
        ]);

        $this->recent = array_slice($this->recent, 0, 10);
    }
}
