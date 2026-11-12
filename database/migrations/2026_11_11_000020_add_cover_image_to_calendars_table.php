<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            // The builder lets creators upload a cover photo, which is sent
            // as a resized base64 data URI, hence longText rather than string.
            $table->longText('cover_image')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('calendars', function (Blueprint $table) {
            $table->dropColumn('cover_image');
        });
    }
};
