<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria 2026-05-13 §5 (P0) — cache pra tool MCP `handoff-fetch-summarized`.
 *
 * Wagner gasta 30-90min/dia relendo handoffs (mediana 142 linhas, outlier 2151).
 * Tool resume via LLM em ~150 tokens compact ou ~400 tokens detailed.
 *
 * Cache strategy:
 *  - Keyed por (filename, content_hash MD5) — re-resume só se conteúdo mudou
 *  - MD5 chosen over SHA256: handoffs são repo-internal (não untrusted input
 *    crypto), MD5 mais compacto (32 char vs 64) + collision rate trivial
 *    pra ~100 handoffs total esperados (10k handoffs → P=2.3e-29)
 *  - tokens_in/out + cost_brl pra dashboard custo Jana Pro
 *
 * Multi-tenant (ADR 0093):
 *  - SEM `business_id` — handoffs são repo-wide (governança projeto inteiro,
 *    não business-specific). Tabela compartilhada entre todos os businesses.
 *  - Consistente com `mcp_memory_documents` que também é repo-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_handoff_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');

            // filename do handoff em memory/handoffs/ — ex `2026-05-13-0900-mcp-bugs.md`
            $table->string('filename', 200)->index('idx_handoff_filename');

            // MD5 do conteúdo raw — cache key composto (filename + hash) detecta
            // re-edit do mesmo handoff (handoff é append-only mas pode ter typo fix)
            $table->char('content_hash', 32);

            // Resumos cacheados — 2 formatos (compact ~150 tok, detailed ~400 tok)
            $table->text('summary_compact')->nullable();
            $table->text('summary_detailed')->nullable();

            // Métricas custo (pra dashboard Jana Pro consolidado)
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_brl', 10, 6)->default(0)
                ->comment('Custo R$ acumulado (compact + detailed)');

            // Modelo usado pra rastrear se vale re-resumir após upgrade modelo
            $table->string('model', 50)->default('gpt-4o-mini');

            $table->timestamps();

            // Unique constraint: 1 row por (filename, content_hash) — refaz se conteúdo mudou
            $table->unique(['filename', 'content_hash'], 'uniq_handoff_filename_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_handoff_summaries');
    }
};
