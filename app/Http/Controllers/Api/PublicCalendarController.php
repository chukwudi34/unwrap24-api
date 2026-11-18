<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicCalendarResource;
use App\Models\AnalyticsEvent;
use App\Models\Calendar;
use App\Models\CalendarOpening;
use App\Models\CalendarVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The unauthenticated surface behind a calendar's shareable /c/{slug}
 * link: reading a published calendar, proving the password on a private
 * one, and recording the visit/open/share events that feed the creator
 * dashboard's stats.
 */
class PublicCalendarController extends Controller
{
    /**
     * Show a published calendar by slug. Password protected calendars
     * come back without day content until they are unlocked.
     */
    public function show(string $slug): JsonResponse
    {
        $calendar = $this->findShareable($slug);

        abort_unless($calendar, 404);

        return response()->json([
            'data' => new PublicCalendarResource($calendar),
        ]);
    }

    /**
     * Verify a private calendar's password and return the calendar with
     * its day content included.
     */
    public function unlock(Request $request, string $slug): JsonResponse
    {
        $calendar = $this->findShareable($slug);

        abort_unless($calendar, 404);

        if ($calendar->access_password === null) {
            return response()->json([
                'data' => (new PublicCalendarResource($calendar))->includeDays(),
            ]);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $calendar->access_password)) {
            throw ValidationException::withMessages([
                'password' => 'That password does not match this calendar. Check your invitation and try again.',
            ]);
        }

        $calendar->load(['days', 'category', 'user']);

        return response()->json([
            'data' => (new PublicCalendarResource($calendar))->includeDays(),
        ]);
    }

    /**
     * Record a page view so the dashboard's visit stats stay real.
     */
    public function visit(Request $request, string $slug): JsonResponse
    {
        $calendar = $this->findShareable($slug);

        abort_unless($calendar, 404);

        CalendarVisit::create([
            'calendar_id' => $calendar->id,
            'visitor_hash' => $this->visitorHash($request),
            'user_agent' => Str::limit($request->userAgent() ?? '', 250, ''),
            'referrer' => Str::limit($request->header('Referer') ?? '', 250, ''),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Record a door opening for a day that is already unlocked.
     */
    public function open(Request $request, string $slug): JsonResponse
    {
        $calendar = $this->findShareable($slug);

        abort_unless($calendar, 404);

        $validated = $request->validate([
            'day_number' => ['required', 'integer', 'min:1', 'max:'.$calendar->day_count],
        ]);

        $day = $calendar->days()
            ->where('day_number', $validated['day_number'])
            ->first();

        abort_unless($day, 404);

        if ($day->unlock_at !== null && $day->unlock_at->isFuture()) {
            return response()->json(['status' => 'locked'], 403);
        }

        CalendarOpening::create([
            'calendar_id' => $calendar->id,
            'calendar_day_id' => $day->id,
            'visitor_hash' => $this->visitorHash($request),
            'opened_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Record that a visitor shared the calendar.
     */
    public function share(Request $request, string $slug): JsonResponse
    {
        $calendar = $this->findShareable($slug);

        abort_unless($calendar, 404);

        AnalyticsEvent::create([
            'calendar_id' => $calendar->id,
            'event_type' => 'share',
            'payload' => ['source' => 'public_page'],
            'visitor_hash' => $this->visitorHash($request),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Published calendars only, resolved by slug (never by ulid) so a
     * draft or archived calendar can't be reached through this API.
     */
    private function findShareable(string $slug): ?Calendar
    {
        if (! preg_match('/^[a-z0-9-]+$/', $slug)) {
            return null;
        }

        return Calendar::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with(['days', 'category', 'user'])
            ->withCount(['visits', 'openings'])
            ->first();
    }

    /**
     * A stable-enough pseudonymous visitor id: IP + user agent. Good
     * enough for unique-visitor counts without setting cookies.
     */
    private function visitorHash(Request $request): string
    {
        return hash('sha256', ($request->ip() ?? '').'|'.($request->userAgent() ?? ''));
    }
}
