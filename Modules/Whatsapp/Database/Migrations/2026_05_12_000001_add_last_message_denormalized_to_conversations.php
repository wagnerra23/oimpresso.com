<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * US-WA-072 — Denormalize last_message_preview/direction em conversations.
 *
 * Problema: `InboxController::convToListArray()` chamava
 * `$c->messages()->reorder('created_at','desc')->first()` pra cada conversa
 * do paginate(50) → 50 queries N+1 no load inicial do `/atendimento/inbox`.
 * Wagner reportou lista pesada (PR #586 já tirou refresh ao trocar thread —
 * ganho ~6x — mas load inicial ainda sofria).
 *
 * Solução: 2 colunas denormalizadas em `conversations`:
 *  - `last_message_preview` VARCHAR(120) — corte 80 chars do body da última msg
 *  - `last_message_direction` ENUM('inbound','outbound') — direção da última msg
 *
 * Mantidas em sync pelo `MessageObserver::created()` (escreve as 2 colunas
 * + last_message_at + last_inbound_at/last_outbound_at em paralelo ao dispatch
 * dos events `OmnichannelMessageReceived/Sent`).
 *
 * Backfill: UPDATE raw SQL pra rows existentes ANTES de virar o controller —
 * preserva preview pra conversas históricas que não vão receber msg nova.
 * Subselect pega body da mensagem mais recente por conversation_id.
 *
 * @see memory/decisions/0135-omnichannel-inbox-arquitetura.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // 120 chars de folga acima dos 80 truncados — permite ajuste futuro
            // sem migration nova (ex: subir pra 100 chars). NULL = conversa sem
            // mensagens (raro mas possível em provisional rows).
            $table->string('last_message_preview', 120)->nullable()->after('unread_count');
            // ENUM espelha `messages.direction` (mesmos valores) — denormalizado
            // pra UI escolher ícone (chevron in vs check out) sem JOIN.
            $table->enum('last_message_direction', ['inbound', 'outbound'])->nullable()->after('last_message_preview');
        });

        // Backfill — só roda se tabela `messages` existir (defesa: ambientes
        // de teste isolados podem criar `conversations` sem `messages`).
        // Raw SQL: evita Eloquent global scope + N queries pra 1k+ rows.
        if (! Schema::hasTable('messages')) {
            return;
        }

        // MySQL: subselect na SET clause permite atualizar 2 colunas baseado
        // na mesma row scan. SQLite (tests): mesmo padrão funciona.
        DB::statement(<<<SQL
            UPDATE conversations c
            SET
                last_message_preview = (
                    SELECT SUBSTRING(m.body, 1, 80)
                    FROM messages m
                    WHERE m.conversation_id = c.id
                    ORDER BY m.created_at DESC
                    LIMIT 1
                ),
                last_message_direction = (
                    SELECT m.direction
                    FROM messages m
                    WHERE m.conversation_id = c.id
                    ORDER BY m.created_at DESC
                    LIMIT 1
                )
        SQL);
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['last_message_preview', 'last_message_direction']);
        });
    }
};
