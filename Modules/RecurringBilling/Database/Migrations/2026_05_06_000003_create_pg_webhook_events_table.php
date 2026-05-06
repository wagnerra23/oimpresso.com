<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pg_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->string('provider', 30)->index(); // asaas | inter | c6
            $table->string('event_id', 100);
            $table->string('event_type', 60);
            $table->json('payload');
            $table->boolean('processed')->default(false)->index();
            $table->timestamps();

            $table->unique(['provider', 'event_id'], 'pg_webhook_idempotency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pg_webhook_events');
    }
};
