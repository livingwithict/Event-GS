<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\ScanActionType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ThirdFactorCheckinController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $expectedKey = config('services.thirdfactor.checkin_api_key');

        if (! $expectedKey || $request->header('X-API-Key') !== $expectedKey) {
            return response(['status' => 'unauthorized'], 401);
        }

        $validated = $request->validate([
            'guest_id' => 'required|string',
            'action' => 'required|string|in:CHECKIN,LUNCH,DINNER',
        ]);

        $registration = Registration::whereRaw('UPPER(guest_number) = ?', [strtoupper($validated['guest_id'])])
            ->first();

        if (! $registration) {
            return response(['status' => 'not_found'], 404);
        }

        $actionType = ScanActionType::where('event_id', $registration->event_id)
            ->where('action_code', $validated['action'])
            ->where('is_active', true)
            ->first();

        if (! $actionType || ! $registration->canPerformAction($actionType->action_code)) {
            return response([
                'status' => 'not_permitted',
                'name' => $registration->displayName(),
                'category' => $registration->category?->name,
            ], 422);
        }

        if (! $registration->recordAction($actionType)) {
            return response([
                'status' => 'already_recorded',
                'at' => $this->alreadyRecordedAt($registration, $actionType)?->format('H:i'),
                'name' => $registration->displayName(),
                'category' => $registration->category?->name,
            ], 200);
        }

        return response([
            'status' => 'recorded',
            'name' => $registration->displayName(),
            'email' => $registration->email,
            'phone' => $registration->phone,
            'category' => $registration->category?->name,
        ], 200);
    }

    private function alreadyRecordedAt(Registration $reg, ScanActionType $action)
    {
        if ($action->column_mapping && $reg->{$action->column_mapping}) {
            return $reg->{$action->column_mapping};
        }

        return $reg->scanLogs()->where('action_type_id', $action->id)->latest('scanned_at')->value('scanned_at');
    }
}
