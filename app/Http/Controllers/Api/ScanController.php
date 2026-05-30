<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ScanResponseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanRequest;
use App\Http\Resources\ScanResponseResource;
use App\Models\Registration;

class ScanController extends Controller
{
    public function store(ScanRequest $request)
    {
        $reg = Registration::where('unique_code', $request->code)->first();

        if (!$reg) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        return response()->json([
            'data' => new ScanResponseResource(ScanResponseDTO::fromModel($reg)),
        ]);
    }
}
