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

        $results = Registration::where('name', 'LIKE', "%{$query}%")
            ->orWhere('email', 'LIKE', "%{$query}%")
            ->orWhere('phone', 'LIKE', "%{$query}%")
            ->limit(20)
            ->get();

        return response()->json(['data' => $results]);
    }
}
