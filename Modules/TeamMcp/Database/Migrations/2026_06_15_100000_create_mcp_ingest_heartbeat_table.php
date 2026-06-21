<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B-LIVE-HB (SDD · ADR 0278) — Heartbeat do ingest (núcleo do fim do SPOF).
 *
 * 1 linha por `host` (máquina/cwd do watcher local de cada dev). Toda vez que
 * POST /api/cc/ingest grava com sucesso, o controller do TeamMcp faz UPSERT
 * idempotente bumpando `last_ingest_at`, `last_session_uuid` e somando `msgs_acc`.
 * Um reader/liveness service (tarefa SEPARADA) lê essa tabela pra detectar SPOF
 * (nenhum ingest recente = watcher caído).
 *
 * FRONTEIRA (refutador): tabela + writer vivem em Modules/TeamMcp (dono do
 * ingest), NÃO em Jana.
 *
 * Tier 0 ({@see ADR 0093}): SEM `business_id` — espelha `mcp_cc_sessions`
 * cross-tenant by design (heartbeat é de infra/máquina, não de tenant).
 * UNIQUE em `host` garante 1 linha por máquina pra upsert.
 *
 * Idempotente (hasTable) + reversível (down dropIfExists).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_ingest_heartbeat')) {
            return;
        }

        Schema::create('mcp_ingest_heartbeat', function (Blueprint $t) {
            $t->bigIncrements('id');

            // Chave de upsert — máquina/cwd do watcher (espelha project_path do session).
            // SEM business_id (cross-tenant, igual mcp_cc_sessions).
            $t->string('host', 500)->unique()
                ->comment('Host/cwd do watcher local (chave de upsert idempotente)');

            $t->timestamp('last_ingest_at')->nullable()
                ->comment('Quando o último POST /api/cc/ingest gravou com sucesso');
            $t->string('last_session_uuid', 36)->nullable()
                ->comment('UUID da última session ingerida por este host');
            $t->unsignedBigInteger('msgs_acc')->default(0)
                ->comment('Acumulador de mensagens ingeridas (msgs_acc += N por POST)');

            $t->timestamps();

            $t->index('last_ingest_at', 'mcp_ingest_hb_last_ingest_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_ingest_heartbeat');
    }
};
