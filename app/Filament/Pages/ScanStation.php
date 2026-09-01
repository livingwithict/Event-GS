<?php

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanActionType;
use App\Services\PickAndDropService;
use App\Services\QRCodeService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ScanStation extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';

    protected static string|UnitEnum|null $navigationGroup = 'Attendees';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Scan Station';

    protected static ?string $title = 'Scan Station';

    protected string $view = 'filament.pages.scan-station';

    /** Sentinel for the "View Status" option — not a real ScanActionType row, so no data ever changes. */
    private const VIEW_STATUS = 'VIEW_STATUS';

    public ?int $eventId = null;

    public int|string|null $actionTypeId = null;

    public string $code = '';

    /** Last scan outcome: ['status' => ok|warning|error, 'title' => .., 'lines' => [..]] */
    public ?array $result = null;

    /** Ten most recent scans this session, newest first. */
    public array $recent = [];

    /** Live name search, View Status only — separate from the exact-match code field. */
    public string $nameQuery = '';

    /** Guests matching nameQuery: ['id', 'label', 'code']. */
    public array $nameResults = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasAbility(Ability::Scan) ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->eventId = session('active_event_id') ?: Event::published()->orderBy('start_datetime')->value('id');
        $this->actionTypeId = $this->actionTypes()->keys()->first();
    }

    public function updatedEventId(): void
    {
        session(['active_event_id' => $this->eventId]);
        $this->actionTypeId = $this->actionTypes()->keys()->first();
        $this->result = null;
        $this->nameQuery = '';
        $this->nameResults = [];
        $this->recent = [];
    }

    public function isViewStatus(): bool
    {
        return $this->actionTypeId === self::VIEW_STATUS;
    }

    /** Fires on every keystroke (wire:model.live) — cheap LIKE search, not the code field's exact match. */
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
            ->get()
            ->map(fn (Registration $r) => [
                'id' => $r->id,
                'label' => $r->displayName(),
                'code' => $r->guest_number,
            ])->all();
    }

    /** @return \Illuminate\Support\Collection<int, string> id => name */
    public function actionTypes()
    {
        if (! $this->eventId) {
            return collect();
        }

        // View Status listed (and thus selected by default via ->keys()->first()) before the real actions.
        return collect([self::VIEW_STATUS => 'View Status'])->union(
            ScanActionType::where('event_id', $this->eventId)
                ->active()
                ->ordered()
                ->pluck('action_name', 'id')
        );
    }

    public function events()
    {
        $user = Auth::user();

        // Qualify the columns: for scoped roles this query joins event_user,
        // where a bare `id` is ambiguous.
        return ($user?->accessibleEvents() ?? Event::query())
            ->orderByDesc('events.start_datetime')
            ->pluck('events.name', 'events.id');
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

        if ($this->actionTypeId === self::VIEW_STATUS) {
            $this->showStatus($code);

            return;
        }

        $action = ScanActionType::find($this->actionTypeId);

        if (! $action || $action->event_id !== $this->eventId || ! $action->is_active) {
            $this->fail('No action selected', ['Pick Entrance, Lunch or Dinner first.']);

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

        if (! $reg->canPerformAction($action->action_code)) {
            $this->fail($reg->displayName(), [$action->action_name.' is not allowed for '.($reg->category?->name ?? 'this category').'.'], $reg);

            return;
        }

        if ($action->action_code === 'CHECKIN' && $reg->approval_status !== 'approved') {
            $this->fail($reg->displayName(), ['Registration is '.$reg->approval_status.', not approved. Send them to the desk.'], $reg);

            return;
        }

        if (! $reg->recordAction($action, Auth::id())) {
            $when = $this->alreadyRecordedAt($reg, $action);

            $this->push('warning', $reg->displayName(), [
                $action->action_name.' already recorded'.($when ? ' at '.$when->format('H:i') : '').'.',
            ], $reg);

            return;
        }

        $lines = array_filter([
            $reg->isPaymentRequired() && $reg->payment_status !== 'paid'
                ? 'Payment outstanding — collect at the desk.'
                : null,
        ]);

        $this->push(
            $reg->isPaymentRequired() && $reg->payment_status !== 'paid' ? 'warning' : 'ok',
            $reg->displayName(),
            $lines,
            $reg,
        );
    }

    /** Look up and display a guest — never records anything. */
    private function showStatus(string $code): void
    {
        $reg = app(QRCodeService::class)->resolve($code);

        if (! $reg) {
            $this->fail('Not found', ["No guest matches \"{$code}\"."]);

            return;
        }

        if ($reg->event_id !== $this->eventId) {
            $this->fail($reg->displayName(), ['This guest belongs to a different event.'], $reg);

            return;
        }

        $this->push('view', $reg->displayName(), [], $reg);
    }

    /** Staff picked one guest off the live name-search results. */
    public function selectNameResult(int $id): void
    {
        $this->nameQuery = '';
        $this->nameResults = [];

        $reg = Registration::where('event_id', $this->eventId)->find($id);

        if (! $reg) {
            $this->fail('Not found', ['That guest no longer exists.']);

            return;
        }

        $this->push('view', $reg->displayName(), [], $reg);
    }

    /** Live courier status for one order, human-readable — or null if the API call fails. */
    private function deliveryStatusLabel(string $orderId): ?string
    {
        $status = app(PickAndDropService::class)->getOrderDetails($orderId)['status'] ?? null;

        return $status ? ucwords(str_replace('_', ' ', $status)) : null;
    }

    private function alreadyRecordedAt(Registration $reg, ScanActionType $action)
    {
        if ($action->column_mapping && $reg->{$action->column_mapping}) {
            return $reg->{$action->column_mapping};
        }

        return $reg->scanLogs()->where('action_type_id', $action->id)->latest('scanned_at')->value('scanned_at');
    }

    /** Label => value pairs shown in the details box, in order. */
    private function guestDetails(Registration $reg, bool $full = false): array
    {
        $details = array_filter([
            'Guest ID' => $reg->guest_number,
            'Category' => $reg->category?->name,
            'Face Verification' => $reg->faceVerificationLabel(),
            'Delivery Status' => $reg->pickndrop_order_id
                ? $this->deliveryStatusLabel($reg->pickndrop_order_id)
                : null,
        ]);

        if ($full) {
            $details['Entrance'] = $reg->entry_time?->format('M j, H:i') ?? 'Not yet';
            $details['Lunch'] = $reg->lunch_used_at?->format('M j, H:i') ?? 'Not yet';
            $details['Dinner'] = $reg->dinner_used_at?->format('M j, H:i') ?? 'Not yet';
        }

        return $details;
    }

    private function fail(string $title, array $lines, ?Registration $reg = null): void
    {
        $this->push('error', $title, $lines, $reg);
    }

    private function push(string $status, string $title, array $lines, ?Registration $reg = null): void
    {
        $isViewOnly = $this->actionTypeId === self::VIEW_STATUS;
        $action = $isViewOnly ? null : ScanActionType::find($this->actionTypeId);

        $this->result = [
            'status' => $status,
            'title' => $title,
            'lines' => array_values($lines),
            'details' => $reg ? $this->guestDetails($reg, $isViewOnly) : [],
            'registration_id' => $reg?->id,
            'can_print_label' => $status !== 'error'
                && $reg
                && ($isViewOnly || $action?->action_code === 'CHECKIN')
                && Auth::user()?->hasAbility(Ability::LabelsPrint)
                && $reg->event->settingEnabled('enable_label_printing'),
        ];

        array_unshift($this->recent, [
            'status' => $status,
            'name' => $reg?->displayName() ?? $title,
            'code' => $reg?->guest_number ?? '',
            'action' => $isViewOnly ? 'View Status' : ($action?->action_name ?? ''),
            'at' => now()->format('H:i:s'),
        ]);

        $this->recent = array_slice($this->recent, 0, 10);
    }
}
