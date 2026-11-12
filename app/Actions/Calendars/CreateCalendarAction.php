<?php

namespace App\Actions\Calendars;

use App\Models\Calendar;
use App\Models\CalendarCategory;
use App\Models\CalendarDay;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateCalendarAction
{
    /** Maps the builder's calendar kind to the `calendars.calendar_type` enum. */
    private const CALENDAR_TYPE_MAP = [
        '7' => '7_day',
        '12' => '12_day',
        '24' => '24_day',
        '30' => '30_day',
        'custom' => 'custom',
    ];

    /** Maps the builder's surprise type to the `calendar_days.content_type` enum. */
    private const CONTENT_TYPE_MAP = [
        'text' => 'rich_text',
        'image' => 'image',
        'video' => 'video',
        'link' => 'external_link',
        'coupon' => 'discount_code',
        'quiz' => 'quiz',
    ];

    /**
     * @param  array<string, mixed>  $data  The validated StoreCalendarRequest payload.
     */
    public function handle(User $user, array $data): Calendar
    {
        $dayCount = $data['calendar_type'] === 'custom'
            ? (int) $data['custom_days']
            : (int) $data['calendar_type'];

        $publishNow = $data['timing'] === 'now';

        $calendar = DB::transaction(function () use ($user, $data, $dayCount, $publishNow) {
            $calendar = Calendar::create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'slug' => $data['slug'],
                'description' => $data['description'] ?? null,
                'cover_image' => $data['cover_image'] ?? null,
                'category_id' => $this->resolveCategoryId($data['category'] ?? null),
                'calendar_type' => self::CALENDAR_TYPE_MAP[$data['calendar_type']],
                'day_count' => $dayCount,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'timezone' => $data['timezone'] ?? 'UTC',
                'theme_config' => $data['theme_config'] ?? null,
                'status' => $publishNow ? 'published' : 'draft',
                'visibility' => $data['visibility'],
                'access_password' => $data['visibility'] === 'private' && ! empty($data['password'])
                    ? Hash::make($data['password'])
                    : null,
                'require_lead_capture' => (bool) ($data['collect_emails'] ?? false),
                'lead_capture_fields' => ($data['collect_emails'] ?? false) ? ['email'] : null,
                'allow_social_sharing' => (bool) ($data['allow_share'] ?? true),
                'allow_comments' => (bool) ($data['allow_comments'] ?? false),
                'published_at' => $publishNow ? now() : null,
                'scheduled_at' => $publishNow ? null : ($data['scheduled_at'] ?? null),
            ]);

            $this->createDays($calendar, $data['days']);

            return $calendar;
        });

        return $calendar->load(['days', 'category']);
    }

    private function resolveCategoryId(?string $category): ?int
    {
        if (! $category) {
            return null;
        }

        return CalendarCategory::where('slug', Str::slug($category))
            ->orWhere('name', $category)
            ->value('id');
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     */
    private function createDays(Calendar $calendar, array $days): void
    {
        foreach ($days as $day) {
            $dayNumber = (int) $day['day_number'];
            $hasContent = trim((string) ($day['title'] ?? '')) !== '';

            CalendarDay::create([
                'calendar_id' => $calendar->id,
                'day_number' => $dayNumber,
                'unlock_at' => Carbon::parse($calendar->start_date)->addDays($dayNumber - 1)->startOfDay(),
                'content_type' => self::CONTENT_TYPE_MAP[$day['type']] ?? 'rich_text',
                'title' => $day['title'] ?? null,
                'body' => $hasContent ? json_encode($this->dayBody($day)) : null,
                'status' => $hasContent ? 'published' : 'empty',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $day
     * @return array<string, mixed>
     */
    private function dayBody(array $day): array
    {
        return array_filter([
            'text' => $day['text'] ?? null,
            'image' => $day['image'] ?? null,
            'video' => $day['video'] ?? null,
            'link_label' => $day['link_label'] ?? null,
            'link_url' => $day['link_url'] ?? null,
            'coupon_code' => $day['coupon_code'] ?? null,
            'coupon_offer' => $day['coupon_offer'] ?? null,
            'coupon_expiry' => $day['coupon_expiry'] ?? null,
            'quiz_question' => $day['quiz_question'] ?? null,
            'quiz_options' => $day['quiz_options'] ?? null,
            'quiz_answer' => $day['quiz_answer'] ?? null,
            'cta_label' => $day['cta_label'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
