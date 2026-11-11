<?php

namespace App\Policies;

use App\Models\Calendar;
use App\Models\User;

/**
 * Phase 1 scaffolding: no calendar CRUD endpoints exist yet, but this
 * policy is registered and ready for Phase 2 controllers to call via
 * `$this->authorize('update', $calendar)` etc.
 */
class CalendarPolicy
{
    /**
     * Anyone can view a published calendar's public page (handled
     * separately, unauthenticated). This `view` ability is for the
     * *owner-facing* view (e.g. the editor/dashboard view of a draft).
     */
    public function view(User $user, Calendar $calendar): bool
    {
        return $this->ownsDirectlyOrViaOrganization($user, $calendar);
    }

    public function update(User $user, Calendar $calendar): bool
    {
        return $this->ownsDirectlyOrViaOrganization($user, $calendar);
    }

    public function delete(User $user, Calendar $calendar): bool
    {
        return $this->ownsDirectlyOrViaOrganization($user, $calendar);
    }

    /**
     * True if the user directly owns the calendar, or belongs to the
     * organization that owns it.
     */
    protected function ownsDirectlyOrViaOrganization(User $user, Calendar $calendar): bool
    {
        if ($calendar->user_id === $user->id) {
            return true;
        }

        if ($calendar->organization_id === null) {
            return false;
        }

        return $user->organizations()
            ->whereKey($calendar->organization_id)
            ->exists();
    }
}
