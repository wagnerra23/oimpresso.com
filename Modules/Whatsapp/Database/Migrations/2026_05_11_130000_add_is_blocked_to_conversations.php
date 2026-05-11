<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-066 — Bloquear contato.
 *
 * Wagner 2026-05-11: "Botão Bloquear no sidebar direito. Quando blocked:
 * webhook inbound de conv blocked é DROPPED (não persistir Message nova) —
 * proteção contra spam. Composer disabled. Botão vira Desbloquear."
 *
 * Schema:
 *  - `conversations.is_blocked` BOOLEAN DEFAULT FALSE NOT NULL — flag de bloqueio.
 *
 * Daemon CT 100 fluxo (parqueado — documentar como follow-up):
 *  - `POST /instances/{id}/block` body `{jid, action}` chama
 *    `sock.updateBlockStatus(jid, 'block'|'unblock')` no Baileys.
 *  - Endpoint Laravel TOLERA 404 do daemon (graceful) — apenas persiste
 *    is_blocked=true sem erro pro user.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_blocked')->default(false)->after('bot_handling');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('is_blocked');
        });
    }
};
