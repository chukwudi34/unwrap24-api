<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->enum('visibility', ['public', 'unlisted', 'private'])->default('public')->after('status');
            $table->string('access_password')->nullable()->after('visibility');
            $table->boolean('allow_comments')->default(false)->after('allow_social_sharing');
            $table->dateTime('scheduled_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->dropColumn(['visibility', 'access_password', 'allow_comments', 'scheduled_at']);
        });
    }
};
