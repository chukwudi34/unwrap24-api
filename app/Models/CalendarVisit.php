<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'calendar_id',
        'visitor_hash',
        'user_agent',
        'referrer',
    ];

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
