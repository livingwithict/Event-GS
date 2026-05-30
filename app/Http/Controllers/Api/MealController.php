<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MealRequest;
use App\Models\Registration;

class MealController extends Controller
{
    public function store(MealRequest $request)
    {
        $reg = Registration::findOrFail($request->registration_id);
        $mealType = $request->meal_type;

        if (!$reg->recordMeal($mealType)) {
            $label = ucfirst($mealType);
            return response()->json(['message' => "{$label} already recorded for this guest."], 409);
        }

        return response()->json(['message' => ucfirst($mealType) . ' recorded.']);
    }
}
