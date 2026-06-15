<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20|regex:/^(\+977|0)?9\d{9}$/',
            'category_id' => 'nullable|exists:participant_categories,id',
            'designation' => 'nullable|string|max:255',
            'organization' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:65535',
            'gender' => 'nullable|in:male,female,other',
            'pan_vat' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:1000',
            'meal_preference' => 'nullable|string|max:50',
            'special_assistance' => 'nullable|string|max:500',
            'promo_code' => 'nullable|string|max:50',
            'companion_count' => 'nullable|integer|min:0|max:10',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'consent' => 'required|accepted',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $email = $this->input('email');
            $phone = $this->input('phone');

            if (empty($email) && empty($phone)) {
                $validator->errors()->add('email', 'At least email or phone is required.');
            }
        });
    }
}
