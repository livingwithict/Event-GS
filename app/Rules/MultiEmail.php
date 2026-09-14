<?php

namespace App\Rules;

use App\Models\Registration;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A guest may list more than one email address, comma-separated — each part must be a valid email. */
class MultiEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (Registration::splitMultiValue((string) $value) as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $fail("The {$attribute} must be a valid email address, or comma-separated list of valid email addresses.");

                return;
            }
        }
    }
}
