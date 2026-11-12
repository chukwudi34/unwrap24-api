<?php

namespace App\Http\Resources;

use App\Models\Calendar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lightweight calendar shape for the dashboard's "Your Calendars" widget:
 * just enough to render the row, plus the engagement counts the widget
 * needs. Expects `visits_count` and `openings_count` to already be loaded
 * via `withCount(['visits', 'openings'])`.
 *
 * @mixin Calendar
 */
class DashboardCalendarResource extends JsonResource
{
    private const TYPE_LABELS = [
        '7_day' => '7 Days',
        '12_day' => '12 Days',
        '24_day' => '24 Days',
        '30_day' => '30 Days',
    ];

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
            'type_label' => self::TYPE_LABELS[$this->calendar_type] ?? "{$this->day_count} Days",
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'visits_count' => $this->visits_count ?? 0,
            'door_openings_count' => $this->openings_count ?? 0,
            'created_at' => $this->created_at,
        ];
    }
}
