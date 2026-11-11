<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'calendar_id',
        'name',
        'email',
        'custom_fields',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'custom_fields' => 'array',
            'consented_at' => 'datetime',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }
}
