<?php

namespace App\Events;

use App\Models\Registration;
use Illuminate\Foundation\Events\Dispatchable;

class EntryRecorded
{
    use Dispatchable;

    public function __construct(public Registration $registration) {}
}
