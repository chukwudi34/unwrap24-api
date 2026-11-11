<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Auto-generates a ULID into the model's `ulid` column on creation.
 *
 * This is intentionally separate from Eloquent's built-in HasUlids trait,
 * which replaces the primary key. Here `id` stays an auto-increment bigint
 * primary key, and `ulid` is a secondary, indexed, publicly-exposable
 * identifier (per the product spec: never expose internal auto-increment
 * IDs in public URLs).
 */
trait HasUlidIdentifier
{
    public static function bootHasUlidIdentifier(): void
    {
        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = (string) Str::ulid();
            }
        });
    }
}
