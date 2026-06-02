<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScanActionRequest;
use App\Models\Event;
use App\Models\Registration;
use App\Models\ScanActionType;

class ScanActionController extends Controller
{
    public function store(ScanActionRequest $request)
    {
        $reg = Registration::findOrFail($request->registration_id);
        $actionType = ScanActionType::findOrFail($request->action_type_id);

        if ($actionType->event_id !== $reg->event_id) {
            return response()->json(['message' => 'This action does not belong to the same event as the participant.'], 403);
        }

        if (! $actionType->is_active) {
            return response()->json(['message' => 'This action type is disabled.'], 422);
        }

        $scannedBy = $request->user()?->id;

        if (! $reg->recordAction($actionType, $scannedBy)) {
            return response()->json(['message' => "{$actionType->action_name} already recorded for this guest."], 409);
        }

        return response()->json(['message' => "{$actionType->action_name} recorded."]);
    }

    public function index($eventId)
    {
        $day = request('day');
        $event = Event::find($eventId);

        $query = ScanActionType::where('event_id', $eventId)
            ->active()
            ->ordered();

        if ($day && $event?->isMultiDay()) {
            $query->where('action_code', 'LIKE', 'DAY'.$day.'_%');
        }

        $actions = $query->get()
            ->map(fn ($action) => [
                'id' => $action->id,
                'action_name' => $action->action_name,
                'action_code' => $action->action_code,
                'allow_multiple' => $action->allow_multiple,
                'sort_order' => $action->sort_order,
            ]);

        $response = ['data' => $actions];

        if ($event) {
            $response['event_context'] = [
                'is_multi_day' => $event->isMultiDay(),
                'current_day' => $event->getCurrentDay(),
                'total_days' => $event->getTotalDays(),
            ];
        }

        return response()->json($response);
    }
}
