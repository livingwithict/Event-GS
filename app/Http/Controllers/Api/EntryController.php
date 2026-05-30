<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EntryRequest;
use App\Models\Registration;

class EntryController extends Controller
{
    public function store(EntryRequest $request)
    {
        $reg = Registration::findOrFail($request->registration_id);

        if (!$reg->recordEntry()) {
            return response()->json(['message' => 'Entry already recorded.'], 409);
        }

        return response()->json(['message' => 'Entry recorded.']);
    }
}
