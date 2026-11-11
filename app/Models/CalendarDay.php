<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'day_number',
        'unlock_at',
        'content_type',
        'title',
        'body',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'unlock_at' => 'datetime',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(CalendarMedia::class);
    }

    public function openings(): HasMany
    {
        return $this->hasMany(CalendarOpening::class);
    }
}
