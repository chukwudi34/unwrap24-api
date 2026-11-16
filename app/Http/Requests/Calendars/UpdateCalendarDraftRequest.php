<?php

namespace App\Http\Requests\Calendars;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCalendarDraftRequest extends FormRequest
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
            'payload' => ['required', 'array'],
            'step' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
