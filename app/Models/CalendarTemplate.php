<?php

namespace App\Models;

use App\Models\Concerns\HasUlidIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarTemplate extends Model
{
    use HasFactory, HasUlidIdentifier;

    protected $fillable = [
        'name',
        'slug',
        'category_id',
        'preview_image_path',
        'config',
        'is_premium',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_premium' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CalendarCategory::class, 'category_id');
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'template_id');
    }
}
