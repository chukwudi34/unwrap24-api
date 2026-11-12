<?php

namespace App\Http\Controllers\Api;

use App\Actions\Calendars\CreateCalendarAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Calendars\StoreCalendarRequest;
use App\Http\Resources\CalendarResource;
use App\Models\Calendar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(private CreateCalendarAction $createCalendar) {}

    /**
     * List calendars owned by the authenticated user (directly, or via an
     * organization they belong to), most recent first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizationIds = $user->organizations()->pluck('organizations.id');

        $calendars = Calendar::query()
            ->where('user_id', $user->id)
            ->when($organizationIds->isNotEmpty(), function ($query) use ($organizationIds) {
                $query->orWhereIn('organization_id', $organizationIds);
            })
            ->with('category')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => CalendarResource::collection($calendars),
        ]);
    }

    public function store(StoreCalendarRequest $request): JsonResponse
    {
        $calendar = $this->createCalendar->handle($request->user(), $request->validated());

        return response()->json([
            'message' => $calendar->status === 'published'
                ? 'Calendar published successfully.'
                : 'Calendar scheduled successfully.',
            'data' => new CalendarResource($calendar),
        ], 201);
    }

    public function show(Request $request, Calendar $calendar): JsonResponse
    {
        $this->authorize('view', $calendar);

        return response()->json([
            'data' => new CalendarResource($calendar->load(['days', 'category'])),
        ]);
    }
}
