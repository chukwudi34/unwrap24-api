<?php

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

class CreateCheckoutSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
            'interval' => ['required', 'in:month,year'],
            'organization_id' => ['nullable', 'string', 'exists:organizations,ulid'],
        ];
    }
}
