<?php

namespace App\Http\Controllers\Api\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * The authenticated user's own active subscription (their personal
     * plan, or -- if they belong to one -- the organization's Business
     * subscription). Used to gate upgrade prompts and show the current
     * plan in the dashboard; never used to decide access on its own.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->activeSubscription();

        if (! $subscription) {
            $organization = $user->ownedOrganizations()->first();
            $subscription = $organization?->activeSubscription();
        }

        return response()->json([
            'data' => $subscription ? new SubscriptionResource($subscription->load('plan')) : null,
        ]);
    }
}
