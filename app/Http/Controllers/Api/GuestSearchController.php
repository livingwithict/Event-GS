<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesEventAccess;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\Request;

class GuestSearchController extends Controller
{
    use AuthorizesEventAccess;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'event_id' => 'required|integer|exists:events,id',
        ]);
        $event = Event::findOrFail($validated['event_id']);
        $this->authorizeEventAccess($event, ['scanner', 'manager', 'admin', 'super_admin']);
        $query = $validated['q'];

        // Try full-text search first (PostgreSQL only)
        try {
            $results = Registration::where('event_id', $event->id)
                ->whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$query])
                ->selectRaw("*, ts_headline('english', coalesce(name, ''), plainto_tsquery('english', ?), 'MaxFragments=1, MinWords=15, MaxWords=35') as highlighted_name", [$query])
                ->orderByRaw("ts_rank(search_vector, plainto_tsquery('english', ?)) DESC", [$query])
                ->limit(20)
                ->get();

            // Fall back to LIKE if no results
            if ($results->isEmpty()) {
                $results = Registration::where('event_id', $event->id)
                    ->where(function ($q) use ($query) {
                        $q->where('name', 'LIKE', "%{$query}%")
                            ->orWhere('guest_number', 'LIKE', "%{$query}%");
                    })
                    ->limit(20)
                    ->get();
            }
        } catch (\Throwable $e) {
            $results = Registration::where('event_id', $event->id)
                ->where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                        ->orWhere('guest_number', 'LIKE', "%{$query}%");
                })
                ->limit(20)
                ->get();
        }

        return response()->json([
            'data' => $results->map(fn (Registration $registration) => [
                'id' => $registration->id,
                'name' => $registration->name,
                'guest_number' => $registration->guest_number,
                'organization' => $registration->organization,
                'designation' => $registration->designation,
                'category_id' => $registration->category_id,
                'payment_status' => $registration->payment_status,
                'has_entered' => $registration->hasEntered(),
            ]),
        ]);
    }
}
