<?php

namespace App\Http\Resources;

use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Calendar
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
            'cover_image' => $this->cover_image,
            'calendar_type' => $this->calendar_type,
            'day_count' => $this->day_count,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'timezone' => $this->timezone,
            'theme_config' => $this->theme_config,
            'status' => $this->status,
            'visibility' => $this->visibility,
            'require_lead_capture' => $this->require_lead_capture,
            'allow_social_sharing' => $this->allow_social_sharing,
            'allow_comments' => $this->allow_comments,
            'published_at' => $this->published_at,
            'scheduled_at' => $this->scheduled_at,
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'days' => CalendarDayResource::collection($this->whenLoaded('days')),
            // Engagement counts -- present only when the endpoint loaded
            // them with withCount() (the dashboard's calendar list does).
            'visits_count' => $this->when(isset($this->resource->visits_count), fn () => (int) $this->resource->visits_count),
            'door_openings_count' => $this->when(isset($this->resource->door_openings_count), fn () => (int) $this->resource->door_openings_count),
            'participants_count' => $this->when(isset($this->resource->participants_count), fn () => (int) $this->resource->participants_count),
            'shares_count' => $this->when(isset($this->resource->shares_count), fn () => (int) $this->resource->shares_count),
            'created_at' => $this->created_at,
        ];
    }
}
