<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Illuminate\Http\Request;

class GuestSearchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2']);
        $query = $request->q;

        // Try full-text search first (PostgreSQL only)
        try {
            $results = Registration::whereRaw("search_vector @@ plainto_tsquery('english', ?)", [$query])
                ->orderByRaw("ts_rank(search_vector, plainto_tsquery('english', ?)) DESC", [$query])
                ->limit(20)
                ->get();

            // Fall back to LIKE if no results
            if ($results->isEmpty()) {
                $results = Registration::where('name', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%")
                    ->orWhere('phone', 'LIKE', "%{$query}%")
                    ->limit(20)
                    ->get();
            }
        } catch (\Throwable $e) {
            // Fallback for SQLite (testing) or other DBs
            $results = Registration::where('name', 'LIKE', "%{$query}%")
                ->orWhere('email', 'LIKE', "%{$query}%")
                ->orWhere('phone', 'LIKE', "%{$query}%")
                ->limit(20)
                ->get();
        }

        return response()->json(['data' => $results]);
    }
}
