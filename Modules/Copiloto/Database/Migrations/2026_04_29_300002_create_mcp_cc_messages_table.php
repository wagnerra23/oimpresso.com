<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-CC-1 — Mensagens Claude Code (1 linha por evento JSONL relevante).
 *
 * Filtra ruído via watcher LOCAL (queue-operation, hooks vazios não ingerem).
 * Tool_results pesados ficam comprimidos em mcp_cc_blobs (SHA256-deduplicados).
 *
 * Indices FULLTEXT em content_text → busca textual cross-dev sem custo extra.
 * Custo MySQL Hostinger: zero (já está pago).
 */
class CreateMcpCcMessagesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_cc_messages', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('session_id')
                ->comment('FK mcp_cc_sessions.id');

            // Identificação cross-dev
            $t->string('msg_uuid', 36)->unique()
                ->comment('UUID gerado pelo Claude Code (dedup global)');
            $t->string('parent_uuid', 36)->nullable()
                ->comment('parentUuid pra reconstruir thread');

            // Quem
            $t->unsignedInteger('user_id')->index();
            $t->unsignedInteger('business_id')->nullable()->index();

            // Tipo evento
            $t->enum('msg_type', [
                'user', 'assistant', 'tool_use', 'tool_result',
                'attachment', 'hook', 'system',
            ])->index();
            $t->string('role', 20)->nullable();
            $t->string('tool_name', 100)->nullable()->index()
                ->comment('Bash|Edit|Read|Grep|Glob|Write|WebSearch|Agent|...');

            // Conteúdo
            $t->mediumText('content_text')->nullable()
                ->comment('Texto plano (FULLTEXT)');
            $t->json('content_json')->nullable()
                ->comment('Payload estruturado completo do JSONL');
            $t->unsignedBigInteger('blob_id')->nullable()
                ->comment('FK mcp_cc_blobs.id se conteúdo > 4KB (compactado + dedup)');

            // Custos
            $t->unsignedInteger('tokens_in')->nullable();
            $t->unsignedInteger('tokens_out')->nullable();
            $t->unsignedInteger('cache_read')->nullable();
            $t->unsignedInteger('cache_write')->nullable();
            $t->decimal('cost_usd', 10, 8)->nullable();

            // Tempo
            $t->timestamp('ts')->index();
            $t->timestamps();

            // Foreign keys
            $t->foreign('session_id')->references('id')->on('mcp_cc_sessions')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Indices de busca
            $t->index(['session_id', 'ts'], 'cc_msg_sess_ts_idx');
            $t->index(['user_id', 'ts'], 'cc_msg_user_ts_idx');
            $t->index(['msg_type', 'tool_name', 'ts'], 'cc_msg_type_tool_idx');
            $t->fullText('content_text', 'cc_msg_content_ft');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_cc_messages');
    }
}
