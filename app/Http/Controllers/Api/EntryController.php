<?php

namespace App\Http\Controllers\Api;

use App\Events\EntryRecorded;
use App\Http\Controllers\Controller;
use App\Http\Requests\EntryRequest;
use App\Models\Registration;
use App\Models\ScanActionType;

class EntryController extends Controller
{
    public function store(EntryRequest $request)
    {
        $reg = Registration::findOrFail($request->registration_id);

        if (! $reg->canPerformAction('CHECKIN')) {
            return response()->json(['message' => 'Check-in is not allowed for this guest\'s category.'], 403);
        }

        if (! $reg->recordEntry()) {
            activity()
                ->performedOn($reg)
                ->withProperties(['action' => 'duplicate_entry'])
                ->log('Duplicate entry attempt');

            return response()->json(['message' => 'Entry already recorded.'], 409);
        }

        $this->writeScanLog($reg, 'CHECKIN', $request->user()?->id);

        event(new EntryRecorded($reg));

        return response()->json(['message' => 'Entry recorded.']);
    }

    private function writeScanLog(Registration $reg, string $actionCode, ?int $scannedBy): void
    {
        $actionType = ScanActionType::where('event_id', $reg->event_id)
            ->where('action_code', $actionCode)
            ->first();

        if ($actionType && ! $reg->hasAction($actionType)) {
            $reg->scanLogs()->create([
                'event_id' => $reg->event_id,
                'action_type_id' => $actionType->id,
                'scanned_by' => $scannedBy,
                'scanned_at' => now(),
            ]);
        }
    }
}
