<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitBusinessProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:160'],
            'tax_id' => [
                'required',
                'string',
                'max:40',
                Rule::unique('business_profiles', 'tax_id')->ignore($this->user()->businessProfile?->id),
            ],
            'contact_name' => ['required', 'string', 'max:80'],
            'contact_phone' => ['required', 'string', 'max:40'],
            'billing_email' => ['nullable', 'email', 'max:160'],
        ];
    }
}
