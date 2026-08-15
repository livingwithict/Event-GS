<?php

namespace App\Http\Controllers\Api;

use App\Enums\Ability;
use App\Events\MealUsed;
use App\Http\Controllers\Concerns\AuthorizesEventAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\MealRequest;
use App\Models\Registration;
use App\Models\ScanActionType;

class MealController extends Controller
{
    use AuthorizesEventAccess;

    public function store(MealRequest $request)
    {
        $reg = Registration::findOrFail($request->registration_id);
        $this->authorizeEventAccess($reg->event, Ability::Scan);
        $mealType = $request->meal_type;

        $actionCode = strtoupper($mealType);
        if (! $reg->canPerformAction($actionCode)) {
            return response()->json(['message' => ucfirst($mealType).' is not allowed for this guest\'s category.'], 403);
        }

        if (! $reg->recordMeal($mealType)) {
            activity()
                ->performedOn($reg)
                ->withProperties(['action' => 'duplicate_meal', 'meal_type' => $mealType])
                ->log('Duplicate '.$mealType.' attempt');

            $label = ucfirst($mealType);

            return response()->json(['message' => "{$label} already recorded for this guest."], 409);
        }

        $this->writeScanLog($reg, $actionCode, $request->user()?->id);

        event(new MealUsed($reg, $mealType));

        return response()->json(['message' => ucfirst($mealType).' recorded.']);
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
