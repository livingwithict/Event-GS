<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'registration_id' => 'required|exists:registrations,id',
            'action_type_id' => 'required|exists:scan_action_types,id',
            'remarks' => 'nullable|string|max:1000',
        ];
    }
}
