<?php

namespace App\Http\Controllers\Api;

use App\Enums\Ability;
use App\Http\Controllers\Concerns\AuthorizesEventAccess;
use App\Http\Controllers\Controller;
use App\Jobs\SendBulkEmail;
use App\Jobs\SendBulkSMS;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunicationController extends Controller
{
    use AuthorizesEventAccess;

    public function sendInvites(Request $request, Event $event): JsonResponse
    {
        $this->authorizeEventAccess($event, Ability::CommunicationsSend);
        $validated = $request->validate([
            'type' => 'required|in:email,sms',
            'email_type' => 'nullable|string|in:registration_confirmation,payment_success,payment_failed,event_reminder,post_event_thank_you,invitation,urgent_update',
            'subject' => 'required_if:type,email|string|max:255',
            'message' => 'required_if:type,sms|string|max:160',
            'registration_ids' => 'array',
            'registration_ids.*' => 'exists:registrations,id',
        ]);

        $regIds = $validated['registration_ids'] ?? $event->registrations()->pluck('registrations.id')->toArray();
        $regIds = $event->registrations()->whereKey($regIds)->pluck('id')->toArray();
        $emailType = $validated['email_type'] ?? 'invitation';

        if ($validated['type'] === 'email') {
            dispatch(new SendBulkEmail($regIds, $event->id, $validated['subject'], $emailType));
        } else {
            dispatch(new SendBulkSMS(
                $regIds, $event->id, $validated['message'], $emailType,
                $request->integer('batch_size', 0)
            ));
        }

        return response()->json([
            'message' => ucfirst($validated['type']).' jobs dispatched.',
            'count' => count($regIds),
        ]);
    }
}
