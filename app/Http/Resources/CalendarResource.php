<?php

namespace App\Http\Resources;

use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Calendar
 *
 * Phase 1 note: no calendar CRUD endpoints exist yet. This resource is
 * scaffolded now so Phase 2 controllers have a ready-made, ID-safe shape
 * to return.
 */
class CalendarResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'calendar_type' => $this->calendar_type,
            'day_count' => $this->day_count,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'timezone' => $this->timezone,
            'theme_config' => $this->theme_config,
            'status' => $this->status,
            'allow_social_sharing' => $this->allow_social_sharing,
            'published_at' => $this->published_at,
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
