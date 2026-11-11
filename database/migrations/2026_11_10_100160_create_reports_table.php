<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calendar_id')->nullable()->constrained('calendars')->cascadeOnDelete();
            $table->text('reason');
            $table->enum('status', ['open', 'reviewed', 'dismissed', 'actioned'])->default('open');
            $table->timestamps();

            $table->index('reporter_user_id');
            $table->index('calendar_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
