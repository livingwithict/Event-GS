<?php

namespace App\Http\Controllers\Api;

use App\DTOs\ScanResponseDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScanRequest;
use App\Http\Resources\ScanResponseResource;
use App\Services\QRCodeService;

class ScanController extends Controller
{
    public function __construct(private QRCodeService $qrService) {}

    public function store(ScanRequest $request)
    {
        $reg = $this->qrService->resolve($request->code);

        if (! $reg) {
            return response()->json(['message' => 'Registration not found.'], 404);
        }

        if ($request->filled('event_id') && (int) $request->event_id !== $reg->event_id) {
            return response()->json(['message' => 'This participant does not belong to the selected event.'], 403);
        }

        return response()->json([
            'data' => new ScanResponseResource(ScanResponseDTO::fromModel($reg)),
        ]);
    }
}
