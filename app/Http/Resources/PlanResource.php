<?php

namespace App\Http\Resources;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 */
class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'price_monthly' => (float) $this->price_monthly,
            'price_yearly' => (float) $this->price_yearly,
            'currency' => $this->currency,
            'features' => $this->features ?? [],
            'limits' => $this->limits ?? [],
            // A plan with no price is either Free (go straight to the
            // dashboard) or Enterprise (contact sales) -- never a checkout.
            'purchasable' => (float) $this->price_monthly > 0 || (float) $this->price_yearly > 0,
        ];
    }
}
