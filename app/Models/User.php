<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_path',
        'google_id',
        'stripe_customer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Organizations this user owns.
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /**
     * Organization memberships (including ones the user owns, as a member record).
     */
    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    /**
     * Organizations this user belongs to (via membership).
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Calendars directly owned/created by this user.
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * The user's own active/trialing subscription (organization-owned
     * subscriptions, e.g. for the Business plan, live on Organization
     * instead -- see Organization::activeSubscription()).
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereNull('organization_id')
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The one in-progress "create calendar" draft this user is working on,
     * if any -- persisted server-side so it's scoped to this account rather
     * than to whichever browser happens to be signed in.
     */
    public function calendarDraft(): HasOne
    {
        return $this->hasOne(CalendarDraft::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_user_id');
    }
}
