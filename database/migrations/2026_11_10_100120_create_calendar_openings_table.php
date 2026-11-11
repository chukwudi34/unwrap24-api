<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_openings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('calendars')->cascadeOnDelete();
            $table->foreignId('calendar_day_id')->constrained('calendar_days')->cascadeOnDelete();
            $table->string('visitor_hash');
            $table->dateTime('opened_at');
            $table->timestamp('created_at')->nullable();

            $table->index('calendar_id');
            $table->index(['calendar_day_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_openings');
    }
};
