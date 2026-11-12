<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardCalendarResource;
use App\Models\Calendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardCalendarsController extends Controller
{
    /**
     * The "Your Calendars" dashboard widget: the creator's most recent
     * calendars (directly owned, or via an organization they belong to),
     * with their engagement counts, capped to a small preview list. The
     * full list lives at GET /calendars.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = max(1, min(20, (int) $request->query('limit', 5)));

        $organizationIds = $user->organizations()->pluck('organizations.id');

        $calendars = Calendar::query()
            ->where('user_id', $user->id)
            ->when($organizationIds->isNotEmpty(), function ($query) use ($organizationIds) {
                $query->orWhereIn('organization_id', $organizationIds);
            })
            ->withCount(['visits', 'openings'])
            ->latest()
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => DashboardCalendarResource::collection($calendars),
        ]);
    }
}
