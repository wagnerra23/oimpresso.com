<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-082 — Replay protection HMAC + nonce.
 *
 * Tabela `webhook_nonces` armazena nonces vistos nos últimos 24h pra
 * rejeitar replays. Cron `whatsapp:cleanup-webhook-nonces` purga >24h
 * (TTL implícito via column `created_at`).
 *
 * Idempotente — `if (! Schema::hasTable)` skip.
 *
 * @see Modules/Whatsapp/Http/Middleware/VerifyBaileysWebhookHmac.php
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('webhook_nonces')) {
            return;
        }

        Schema::create('webhook_nonces', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('nonce', 64)->unique();
            $table->string('source', 32); // 'baileys', 'meta', etc
            $table->timestamp('created_at');
            $table->index('created_at', 'webhook_nonces_created_at_idx');
        });
    }

    public function down(): void
    {
        // NÃO dropamos — risco de aceitar replays se rollback parcial.
        // Manual via tinker se realmente precisar.
    }
};
