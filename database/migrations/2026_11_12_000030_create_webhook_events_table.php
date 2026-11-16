<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ledger of processed payment-provider webhook event ids. Stripe
     * (and Paystack) deliver webhooks at-least-once, so every handler
     * records the event id here first and skips anything already seen,
     * instead of re-applying the same subscription/payment change twice.
     */
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->enum('provider', ['stripe', 'paystack']);
            $table->string('event_id');
            $table->string('event_type');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
