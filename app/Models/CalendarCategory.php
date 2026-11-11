<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalendarCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort_order',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function templates(): HasMany
    {
        return $this->hasMany(CalendarTemplate::class, 'category_id');
    }

    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class, 'category_id');
    }
}
