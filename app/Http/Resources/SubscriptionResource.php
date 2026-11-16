<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ulid' => $this->ulid,
            'status' => $this->status,
            'interval' => $this->interval,
            'provider' => $this->provider,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'cancel_at_period_end' => $this->cancel_at_period_end,
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
