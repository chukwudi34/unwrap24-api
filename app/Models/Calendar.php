<?php

namespace App\Models;

use App\Models\Concerns\HasUlidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Calendar extends Model
{
    use HasFactory, HasUlidIdentifier, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'user_id',
        'title',
        'slug',
        'description',
        'category_id',
        'template_id',
        'calendar_type',
        'day_count',
        'start_date',
        'end_date',
        'timezone',
        'theme_config',
        'status',
        'require_lead_capture',
        'lead_capture_fields',
        'allow_social_sharing',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'theme_config' => 'array',
            'lead_capture_fields' => 'array',
            'require_lead_capture' => 'boolean',
            'allow_social_sharing' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Resolve route binding by ulid or slug, never the internal
     * auto-increment id, for any future public-facing route.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('ulid', $value)
            ->orWhere('slug', $value)
            ->first();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CalendarCategory::class, 'category_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CalendarTemplate::class, 'template_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(CalendarDay::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(CalendarMedia::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(CalendarVisit::class);
    }

    public function openings(): HasMany
    {
        return $this->hasMany(CalendarOpening::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
