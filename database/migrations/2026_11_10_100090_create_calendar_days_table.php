<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('calendars')->cascadeOnDelete();
            $table->unsignedInteger('day_number');
            $table->dateTime('unlock_at');
            $table->enum('content_type', [
                'rich_text', 'image', 'video', 'external_link', 'file',
                'discount_code', 'promo_offer', 'quiz', 'poll',
            ]);
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->enum('status', ['empty', 'draft', 'scheduled', 'published'])->default('empty');
            $table->timestamps();

            $table->unique(['calendar_id', 'day_number']);
            $table->index('unlock_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_days');
    }
};
