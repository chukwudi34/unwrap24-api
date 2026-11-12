<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Calendar;
use App\Models\CalendarOpening;
use App\Models\CalendarVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardStatsController extends Controller
{
    /**
     * Return creator dashboard stats scoped to the authenticated user:
     * calendars they own directly, plus calendars owned by any
     * organization they are a member of.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $organizationIds = $user->organizations()->pluck('organizations.id');

        $calendarIds = Calendar::query()
            ->where('user_id', $user->id)
            ->when($organizationIds->isNotEmpty(), function ($query) use ($organizationIds) {
                $query->orWhereIn('organization_id', $organizationIds);
            })
            ->pluck('id');

        $totalCalendars = $calendarIds->count();

        $publishedCalendars = Calendar::whereIn('id', $calendarIds)
            ->where('status', 'published')
            ->count();

        $draftCalendars = Calendar::whereIn('id', $calendarIds)
            ->where('status', 'draft')
            ->count();

        $totalVisits = CalendarVisit::whereIn('calendar_id', $calendarIds)->count();

        $uniqueVisitors = CalendarVisit::whereIn('calendar_id', $calendarIds)
            ->distinct('visitor_hash')
            ->count('visitor_hash');

        $totalDoorOpenings = CalendarOpening::whereIn('calendar_id', $calendarIds)->count();

        $totalShares = AnalyticsEvent::whereIn('calendar_id', $calendarIds)
            ->where('event_type', 'share')
            ->count();

        return response()->json([
            'data' => [
                'total_calendars' => $totalCalendars,
                'published_calendars' => $publishedCalendars,
                'draft_calendars' => $draftCalendars,
                'total_visits' => $totalVisits,
                'unique_visitors' => $uniqueVisitors,
                'total_door_openings' => $totalDoorOpenings,
                'total_shares' => $totalShares,
                // Real activity feed is deferred to Phase 2+.
                'recent_activity' => [],
            ],
        ]);
    }
}
