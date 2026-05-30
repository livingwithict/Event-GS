<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkEmail;
use App\Jobs\SendBulkSMS;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    public function sendInvites(Request $request, Event $event): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:email,sms',
            'subject' => 'required_if:type,email|string|max:255',
            'message' => 'required_if:type,sms|string|max:160',
            'registration_ids' => 'array',
            'registration_ids.*' => 'exists:registrations,id',
        ]);

        $regIds = $validated['registration_ids'] ?? $event->registrations()->pluck('registrations.id')->toArray();

        if ($validated['type'] === 'email') {
            dispatch(new SendBulkEmail($regIds, $event->id, $validated['subject']));
        } else {
            dispatch(new SendBulkSMS($regIds, $event->id, $validated['message']));
        }

        return response()->json([
            'message' => ucfirst($validated['type']) . ' jobs dispatched.',
            'count' => count($regIds),
        ]);
    }
}
