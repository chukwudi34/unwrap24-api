<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarMedia extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'calendar_day_id',
        'disk',
        'path',
        'mime_type',
        'size',
        'original_filename',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    public function calendarDay(): BelongsTo
    {
        return $this->belongsTo(CalendarDay::class);
    }
}
