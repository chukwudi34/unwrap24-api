<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendars', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('calendar_categories')->nullOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('calendar_templates')->nullOnDelete();
            $table->enum('calendar_type', ['7_day', '12_day', '24_day', '30_day', 'custom']);
            $table->unsignedInteger('day_count');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('timezone')->default('UTC');
            $table->json('theme_config')->nullable();
            $table->enum('status', ['draft', 'published', 'unpublished', 'archived'])->default('draft');
            $table->boolean('require_lead_capture')->default(false);
            $table->json('lead_capture_fields')->nullable();
            $table->boolean('allow_social_sharing')->default(true);
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('organization_id');
            $table->index('user_id');
            $table->index('category_id');
            $table->index('template_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendars');
    }
};
