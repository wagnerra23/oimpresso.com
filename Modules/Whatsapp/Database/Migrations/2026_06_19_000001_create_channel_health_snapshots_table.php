<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * channel_health_snapshots — série temporal append-only de `channel_health` por
 * canal (ADR 0288). Base pra SLIs (uptime%, time-to-detect) + alerta canal-down.
 * Append-only (nunca UPDATE/DELETE no fluxo normal); retenção via job futuro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('channel_health_snapshots')) {
            return; // idempotente
        }

        Schema::create('channel_health_snapshots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');           // Tier 0 (ADR 0093)
            $table->unsignedBigInteger('channel_id');
            $table->string('channel_health', 20);
            $table->timestamp('recorded_at')->useCurrent();

            $table->index(['channel_id', 'recorded_at'], 'chs_channel_recorded_idx'); // streak/uptime
            $table->index(['business_id', 'recorded_at'], 'chs_biz_recorded_idx');     // agregação por tenant
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_health_snapshots');
    }
};
