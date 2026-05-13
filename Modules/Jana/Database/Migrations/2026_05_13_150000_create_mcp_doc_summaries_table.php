<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 5 — Agent A1 (Auto-summary docs longos).
 *
 * Cache MySQL pra Modules\Jana\Services\Summarizer\AutoSummarizerService.
 * Map-reduce LLM gpt-4o-mini compacta docs > 8KB em summary cacheado 24h.
 *
 * Cache strategy:
 *  - Keyed por (content_hash MD5, model) — re-resume só se conteúdo OU modelo mudou
 *  - MD5 chosen over SHA256: docs são repo-internal (não untrusted input crypto)
 *  - cost_brl gravado por call pra hard-enforce cap mensal JANA_SUMMARIZER_MAX_COST_BRL
 *  - tokens_in / tokens_out reportam telemetria fina (Langfuse Onda 4 lê isto)
 *
 * Multi-tenant Tier 0 (ADR 0093):
 *  - SEM `business_id` — docs canon (ADRs, SPECs, KB) são repo-wide. Summary do
 *    mesmo doc_hash serve qualquer business; não há leak cross-tenant porque o
 *    CONTEÚDO já é shared (CLAUDE.md, ADR 0094 etc).
 *  - Consistente com `mcp_memory_documents` + `mcp_handoff_summaries` que
 *    também são repo-wide.
 *  - Tools que chamam o summarizer (decisions-fetch, tasks-detail, kb-answer)
 *    ANTES aplicam scope multi-tenant — summary só nasce após filtro de
 *    permissões na camada da tool.
 *
 * @see memory/requisitos/Jana/ONDA-5-DOSSIER-2026-05-13.md §A1
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md (exceções repo-wide)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_doc_summaries', function (Blueprint $table) {
            $table->bigIncrements('id');

            // MD5 do conteúdo raw — cache key composto (hash + model)
            $table->char('content_hash', 32)->index('idx_doc_summary_hash');

            // Tamanho original em chars (pra estatística: redução média)
            $table->unsignedInteger('original_size');

            // Summary final (map-reduce output ~1500 tokens default)
            $table->text('summary');

            // Métricas pra cap mensal + telemetria Langfuse
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_brl', 10, 6)->default(0)
                ->comment('Custo R$ acumulado (USD * câmbio config)');

            // Modelo usado — permite re-resumir após upgrade modelo
            $table->string('model', 50)->default('gpt-4o-mini');

            $table->timestamps();

            // Unique: 1 row por (content_hash, model) — refaz se conteúdo OU modelo muda
            $table->unique(['content_hash', 'model'], 'uniq_doc_summary_hash_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_doc_summaries');
    }
};
