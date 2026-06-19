<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\ParticipantCategory;
use App\Models\Registration;
use App\Services\CommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnsiteRegistrationController extends Controller
{
    public function show(Event $event)
    {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin', 'manager'])) {
            abort(403);
        }

        $event->load(['categories' => fn ($q) => $q->active()->ordered()]);

        return view('register.onsite', [
            'event' => $event,
            'categories' => $event->categories,
        ]);
    }

    public function store(Request $request, Event $event)
    {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin', 'manager'])) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20|regex:/^(\+977|0)?9\d{9}$/',
            'category_id' => 'nullable|exists:participant_categories,id',
            'organization' => 'nullable|string|max:255',
            'designation' => 'nullable|string|max:255',
            'meal_preference' => 'nullable|in:veg,non-veg,vegan,halal',
            'special_assistance' => 'nullable|string|max:500',
            'send_notifications' => 'boolean',
        ]);

        $email = trim($validated['email'] ?? '');
        $phone = trim($validated['phone'] ?? '');

        if (empty($email) && empty($phone)) {
            return back()->withInput()->withErrors(['email' => 'At least email or phone is required.']);
        }

        $categoryId = $validated['category_id'] ?? null;
        if ($categoryId) {
            $category = ParticipantCategory::where('id', $categoryId)
                ->where('event_id', $event->id)
                ->where('is_active', true)
                ->first();
            if (! $category) {
                return back()->withInput()->withErrors(['category_id' => 'Invalid category.']);
            }
        }

        $reg = Registration::create([
            'event_id' => $event->id,
            'category_id' => $categoryId,
            'registration_source' => 'admin_manual',
            'approval_status' => 'approved',
            'name' => trim($validated['name']),
            'email' => $email ?: null,
            'phone' => $phone ?: null,
            'organization' => trim($validated['organization'] ?? '') ?: null,
            'designation' => trim($validated['designation'] ?? '') ?: null,
            'meal_preference' => $validated['meal_preference'] ?? null,
            'special_assistance' => trim($validated['special_assistance'] ?? '') ?: null,
            'consented_at' => now(),
        ]);

        if ($request->boolean('send_notifications')) {
            try {
                $commService = new CommunicationService;
                $commService->sendRegistrationConfirmation($reg, $event);
            } catch (\Throwable $e) {
                logger()->error('Onsite registration notification failed: '.$e->getMessage());
            }
        }

        return redirect()->route('onsite.register', $event->id)
            ->with('success', "{$reg->name} registered! Guest #: {$reg->guest_number}");
    }
}
