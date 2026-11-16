<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendars\UpdateCalendarDraftRequest;
use App\Http\Resources\CalendarDraftResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarDraftController extends Controller
{
    /**
     * The authenticated user's in-progress "create calendar" draft, if any.
     * Scoped to the account rather than a browser, so it never leaks to
     * whoever else signs in on the same device, and resumes on any device.
     */
    public function show(Request $request): JsonResponse
    {
        $draft = $request->user()->calendarDraft;

        return response()->json([
            'data' => $draft ? new CalendarDraftResource($draft) : null,
        ]);
    }

    public function update(UpdateCalendarDraftRequest $request): JsonResponse
    {
        $draft = $request->user()->calendarDraft()->updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'payload' => $request->validated('payload'),
                'step' => $request->validated('step') ?? 1,
            ],
        );

        return response()->json([
            'data' => new CalendarDraftResource($draft),
        ]);
    }

    /**
     * Clear the draft -- called once a calendar is actually published, since
     * at that point the draft is final and should stop being offered back.
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->calendarDraft()->delete();

        return response()->json(['data' => null]);
    }
}
