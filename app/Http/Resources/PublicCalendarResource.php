<?php

namespace App\Http\Resources;

use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The unauthenticated shape of a calendar on its public /c/{slug} page.
 * Never exposes the raw password: `has_password` only tells the frontend
 * to render the gate, and the real day content is withheld until the
 * visitor proves they know the password (see PublicCalendarController
 *
 * @unlock).
 *
 * @mixin Calendar
 */
class PublicCalendarResource extends JsonResource
{
    /**
     * Whether day content may be included in the payload. Password
     * protected calendars only reveal their days through the unlock
     * endpoint, which sets this before serializing.
     */
    public bool $withDays = false;

    public function includeDays(bool $value = true): static
    {
        $this->withDays = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $calendar = $this->resource;
        $hasPassword = $calendar->access_password !== null;

        return [
            'ulid' => $calendar->ulid,
            'slug' => $calendar->slug,
            'title' => $calendar->title,
            'description' => $calendar->description,
            'cover_image' => $calendar->cover_image,
            'calendar_type' => $calendar->calendar_type,
            'day_count' => $calendar->day_count,
            'start_date' => $calendar->start_date,
            'end_date' => $calendar->end_date,
            'timezone' => $calendar->timezone,
            'theme_config' => $calendar->theme_config,
            'status' => $calendar->status,
            'visibility' => $calendar->visibility,
            'has_password' => $hasPassword,
            'allow_social_sharing' => $calendar->allow_social_sharing,
            'published_at' => $calendar->published_at,
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $calendar->category->name,
                'slug' => $calendar->category->slug,
            ]),
            'creator' => [
                'name' => $calendar->user?->name ?? 'Unwrap24 Creator',
                'brand' => $calendar->theme_config['brand_name'] ?? null,
            ],
            'engagement' => [
                'visitors' => $calendar->visits_count ?? 0,
                'opens' => $calendar->openings_count ?? 0,
            ],
            // Day content stays hidden behind the password gate.
            'days' => ($this->withDays || ! $hasPassword)
                ? CalendarDayResource::collection($calendar->days->sortBy('day_number')->values())
                : [],
        ];
    }
}
