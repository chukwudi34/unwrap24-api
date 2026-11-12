<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\CalendarOpening;
use App\Models\CalendarVisit;
use App\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardEngagementController extends Controller
{
    /**
     * Daily engagement series (visits, door openings, new email
     * subscribers) for the authenticated user's calendars, over the last
     * N days (default/clamped to 1-90). Every day in the range is present
     * in the response, even when its count is zero, so the frontend can
     * plot a continuous line without gap-filling itself.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $days = max(1, min(90, (int) $request->query('days', 30)));

        $organizationIds = $user->organizations()->pluck('organizations.id');

        $calendarIds = Calendar::query()
            ->where('user_id', $user->id)
            ->when($organizationIds->isNotEmpty(), function ($query) use ($organizationIds) {
                $query->orWhereIn('organization_id', $organizationIds);
            })
            ->pluck('id');

        $start = CarbonImmutable::now()->subDays($days - 1)->startOfDay();
        $end = CarbonImmutable::now()->endOfDay();

        $visitsByDay = $this->dailyCounts(CalendarVisit::whereIn('calendar_id', $calendarIds), 'created_at', $start, $end);
        $openingsByDay = $this->dailyCounts(CalendarOpening::whereIn('calendar_id', $calendarIds), 'opened_at', $start, $end);
        $subscribersByDay = $this->dailyCounts(Lead::whereIn('calendar_id', $calendarIds), 'created_at', $start, $end);

        $series = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->addDay()) {
            $day = $cursor->toDateString();
            $series[] = [
                'date' => $day,
                'visits' => $visitsByDay[$day] ?? 0,
                'openings' => $openingsByDay[$day] ?? 0,
                'subscribers' => $subscribersByDay[$day] ?? 0,
            ];
        }

        return response()->json(['data' => $series]);
    }

    /**
     * @return array<string, int> Keyed by Y-m-d.
     */
    private function dailyCounts(Builder $query, string $column, CarbonImmutable $start, CarbonImmutable $end): array
    {
        return $query->whereBetween($column, [$start, $end])
            ->selectRaw("DATE({$column}) as day, COUNT(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day')
            ->toArray();
    }
}
