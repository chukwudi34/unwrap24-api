<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A unique index on (provider, provider_reference) so a retried
     * webhook (Stripe/Paystack both deliver at-least-once) can never create
     * a second Payment row for the same provider-side charge/invoice.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['provider', 'provider_reference']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_reference']);
        });
    }
};
