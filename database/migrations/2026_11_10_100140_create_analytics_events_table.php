<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->nullable()->constrained('calendars')->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload')->nullable();
            $table->string('visitor_hash')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('calendar_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
