<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('calendars')->cascadeOnDelete();
            $table->foreignId('calendar_day_id')->nullable()->constrained('calendar_days')->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('original_filename')->nullable();
            $table->timestamps();

            $table->index('calendar_id');
            $table->index('calendar_day_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_media');
    }
};
