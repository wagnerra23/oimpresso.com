<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP D3 #1 — Contextual Retrieval Anthropic (2024-09-19 / oimpresso 2026-05-15).
 *
 * Adiciona 3 colunas em `mcp_memory_documents` pra suportar Contextual
 * Retrieval canônico Anthropic:
 *
 *   - contextual_context (TEXT)         : contexto gerado pelo LLM (50-100 tokens)
 *   - contextual_indexed (BOOLEAN)      : flag se já foi indexado (backfill control)
 *   - contextualized_at (TIMESTAMP)     : quando foi gerado (cache invalidation)
 *
 * Operacional pra ~1.500 docs oimpresso. Backfill via:
 *   php artisan jana:contextualize-backfill --limit=100
 *
 * Tabela `mcp_memory_documents` é repo-wide (sem business_id scope canônico
 * por design — ADR 0053 §Pilar 6). Colunas adicionadas seguem o mesmo padrão
 * (cross-tenant searchable). Multi-tenant Tier 0 preservado: business_id
 * herdado do doc original (já existente na tabela via 2026-04-30 migration).
 *
 * Idempotente: usa Schema::hasColumn antes de adicionar. Reversível: down()
 * remove apenas as 3 colunas adicionadas (preserva dados existentes da tabela).
 *
 * @see Modules/Jana/Services/Memoria/Contextual/ContextualizerService.php
 * @see memory/requisitos/Jana/CONTEXTUAL-RETRIEVAL-ANTHROPIC.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcp_memory_documents')) {
            return; // schema base não existe ainda — nada a fazer
        }

        Schema::table('mcp_memory_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('mcp_memory_documents', 'contextual_context')) {
                $table->text('contextual_context')->nullable()
                    ->after('content_md')
                    ->comment('Contextual Retrieval Anthropic: contexto 50-100 tokens descrevendo doc');
            }

            if (! Schema::hasColumn('mcp_memory_documents', 'contextual_indexed')) {
                $table->boolean('contextual_indexed')->default(false)
                    ->after('contextual_context')
                    ->comment('Flag se contextualização Anthropic já rodou (backfill control)');
            }

            if (! Schema::hasColumn('mcp_memory_documents', 'contextualized_at')) {
                $table->timestamp('contextualized_at')->nullable()
                    ->after('contextual_indexed')
                    ->comment('Quando contextual_context foi gerado (cache invalidation)');
            }
        });

        // Índice em contextual_indexed pra acelerar backfill (WHERE contextual_indexed=false).
        // Nome explícito ≤64 chars (MySQL limit).
        try {
            Schema::table('mcp_memory_documents', function (Blueprint $table) {
                $table->index('contextual_indexed', 'mcp_md_ctx_idx');
            });
        } catch (\Throwable $e) {
            // Index já existe ou DBAL ausente — ignora.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mcp_memory_documents')) {
            return;
        }

        // Drop index primeiro (alguns DBs exigem ordem).
        try {
            Schema::table('mcp_memory_documents', function (Blueprint $table) {
                $table->dropIndex('mcp_md_ctx_idx');
            });
        } catch (\Throwable $e) {
            // Index pode não existir — ignora.
        }

        Schema::table('mcp_memory_documents', function (Blueprint $table) {
            $colunas = array_filter([
                Schema::hasColumn('mcp_memory_documents', 'contextual_context') ? 'contextual_context' : null,
                Schema::hasColumn('mcp_memory_documents', 'contextual_indexed') ? 'contextual_indexed' : null,
                Schema::hasColumn('mcp_memory_documents', 'contextualized_at') ? 'contextualized_at' : null,
            ]);
            if (! empty($colunas)) {
                $table->dropColumn(array_values($colunas));
            }
        });
    }
};
