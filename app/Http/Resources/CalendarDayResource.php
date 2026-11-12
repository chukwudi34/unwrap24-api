<?php

namespace App\Http\Resources;

use App\Models\CalendarDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CalendarDay
 */
class CalendarDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'day_number' => $this->day_number,
            'unlock_at' => $this->unlock_at,
            'content_type' => $this->content_type,
            'title' => $this->title,
            'body' => $this->body ? json_decode($this->body, true) : null,
            'status' => $this->status,
        ];
    }
}
