<?php

namespace App\Filament\Pages;

use App\Enums\Ability;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanActionType;
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

    public ?int $eventId = null;

    public ?int $actionTypeId = null;

    public string $code = '';

    /** Last scan outcome: ['status' => ok|warning|error, 'title' => .., 'lines' => [..]] */
    public ?array $result = null;

    /** Ten most recent scans this session, newest first. */
    public array $recent = [];

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
        $this->recent = [];
    }

    /** @return \Illuminate\Support\Collection<int, string> id => name */
    public function actionTypes()
    {
        if (! $this->eventId) {
            return collect();
        }

        return ScanActionType::where('event_id', $this->eventId)
            ->active()
            ->ordered()
            ->pluck('action_name', 'id');
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

            $this->push('warning', $reg->displayName(), array_filter([
                $action->action_name.' already recorded'.($when ? ' at '.$when->format('H:i') : '').'.',
                $this->guestLine($reg),
            ]), $reg);

            return;
        }

        $lines = array_filter([
            $this->guestLine($reg),
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

    private function alreadyRecordedAt(Registration $reg, ScanActionType $action)
    {
        if ($action->column_mapping && $reg->{$action->column_mapping}) {
            return $reg->{$action->column_mapping};
        }

        return $reg->scanLogs()->where('action_type_id', $action->id)->latest('scanned_at')->value('scanned_at');
    }

    private function guestLine(Registration $reg): string
    {
        return implode(' · ', array_filter([
            $reg->guest_number,
            $reg->category?->name,
            $reg->organization,
        ]));
    }

    private function fail(string $title, array $lines, ?Registration $reg = null): void
    {
        $this->push('error', $title, $lines, $reg);
    }

    private function push(string $status, string $title, array $lines, ?Registration $reg = null): void
    {
        $action = ScanActionType::find($this->actionTypeId);

        $this->result = [
            'status' => $status,
            'title' => $title,
            'lines' => array_values($lines),
            'registration_id' => $reg?->id,
            'can_print_label' => $status !== 'error'
                && $reg
                && $action?->action_code === 'CHECKIN'
                && Auth::user()?->hasAbility(Ability::LabelsPrint)
                && $reg->event->settingEnabled('enable_label_printing'),
        ];

        array_unshift($this->recent, [
            'status' => $status,
            'name' => $reg?->displayName() ?? $title,
            'code' => $reg?->guest_number ?? '',
            'action' => ScanActionType::find($this->actionTypeId)?->action_name ?? '',
            'at' => now()->format('H:i:s'),
        ]);

        $this->recent = array_slice($this->recent, 0, 10);
    }
}
