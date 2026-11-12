<?php

namespace App\Http\Requests\Calendars;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCalendarRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            // The builder sends the cover as a resized base64 data URI, so
            // this allows a generously-sized image rather than a URL-length string.
            'cover_image' => ['nullable', 'string', 'max:6000000'],

            'calendar_type' => ['required', 'in:7,12,24,30,custom'],
            'custom_days' => ['required_if:calendar_type,custom', 'integer', 'min:2', 'max:60'],

            'theme_config' => ['required', 'array'],
            'theme_config.theme' => ['nullable', 'string', 'max:64'],
            'theme_config.primary' => ['nullable', 'string', 'max:32'],
            'theme_config.accent' => ['nullable', 'string', 'max:32'],
            'theme_config.background' => ['nullable', 'string', 'max:32'],
            'theme_config.font' => ['nullable', 'string', 'max:64'],
            'theme_config.door_style' => ['nullable', 'string', 'max:64'],
            'theme_config.layout' => ['nullable', 'string', 'max:64'],
            'theme_config.snowfall' => ['nullable', 'boolean'],
            'theme_config.show_branding' => ['nullable', 'boolean'],
            'theme_config.brand_name' => ['nullable', 'string', 'max:255'],

            'days' => ['required', 'array', 'min:1'],
            'days.*.day_number' => ['required', 'integer', 'min:1'],
            'days.*.title' => ['nullable', 'string', 'max:255'],
            'days.*.type' => ['required', 'in:text,image,video,link,coupon,quiz'],
            'days.*.text' => ['nullable', 'string'],
            'days.*.image' => ['nullable', 'string', 'max:2048'],
            'days.*.video' => ['nullable', 'string', 'max:2048'],
            'days.*.link_label' => ['nullable', 'string', 'max:255'],
            'days.*.link_url' => ['nullable', 'string', 'max:2048'],
            'days.*.coupon_code' => ['nullable', 'string', 'max:64'],
            'days.*.coupon_offer' => ['nullable', 'string', 'max:255'],
            'days.*.coupon_expiry' => ['nullable', 'string', 'max:32'],
            'days.*.quiz_question' => ['nullable', 'string', 'max:255'],
            'days.*.quiz_options' => ['nullable', 'array'],
            'days.*.quiz_options.*' => ['nullable', 'string', 'max:255'],
            'days.*.quiz_answer' => ['nullable', 'integer', 'min:0'],
            'days.*.cta_label' => ['nullable', 'string', 'max:64'],

            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9-]+$/', 'unique:calendars,slug'],
            'visibility' => ['required', 'in:public,unlisted,private'],
            'password' => ['required_if:visibility,private', 'nullable', 'string', 'min:4', 'max:255'],
            'timing' => ['required', 'in:now,scheduled'],
            'scheduled_at' => ['required_if:timing,scheduled', 'nullable', 'date', 'after:now'],
            'allow_share' => ['boolean'],
            'collect_emails' => ['boolean'],
            'allow_comments' => ['boolean'],
        ];
    }

    /**
     * Mirrors the builder's own publish checklist: at least one door needs
     * content before the calendar can be saved as a real campaign.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $days = $this->input('days', []);
            $hasContent = collect($days)->contains(fn ($day) => trim((string) ($day['title'] ?? '')) !== '');

            if (! $hasContent) {
                $validator->errors()->add('days', 'Add content to at least one door before publishing.');
            }
        });
    }
}
