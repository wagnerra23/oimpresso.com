<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-CC-1 / G5 — coluna `model` em mcp_cc_messages.
 *
 * O watcher (scripts/cc-watcher) passa a enviar `model` (ex "claude-opus-4-8") por
 * mensagem assistant; a agregação de custo-por-PR (G5) precisa dele pra precificar
 * tokens_in/out por modelo. Antes o modelo só sobrevivia dentro de content_json
 * (não indexável, e content_json era stripado pelo bug do validate — PR #4503).
 * Nullable: mensagens user/tool_result não têm modelo.
 *
 * Idempotente + reversível (regra .claude/rules/migrations.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_cc_messages') && ! Schema::hasColumn('mcp_cc_messages', 'model')) {
            Schema::table('mcp_cc_messages', function (Blueprint $t) {
                $t->string('model', 60)->nullable()->after('tool_name')
                    ->comment('Modelo LLM da mensagem assistant (ex claude-opus-4-8) — G5 pricing');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mcp_cc_messages') && Schema::hasColumn('mcp_cc_messages', 'model')) {
            Schema::table('mcp_cc_messages', function (Blueprint $t) {
                $t->dropColumn('model');
            });
        }
    }
};
