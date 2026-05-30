<?php

namespace App\Events;

use App\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

class MealUsed
{
    use Dispatchable;

    public function __construct(public Registration $registration, public string $mealType) {}
}
