<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoria 2026-05-13 §5 (P0) — cache pra tool MCP `handoff-diff`.
 *
 * Wagner reabrindo sessão: em vez de reler handoff inteiro (~5k-25k tokens),
 * pede "o que mudou desde último handoff" e recebe ~500 tokens estruturados.
 *
 * Diferença vs `mcp_handoff_summaries` (G4):
 *  - summaries: resume 1 handoff específico
 *  - diffs: agrega eventos N PRs + M ADRs + K cycles desde uma data
 *
 * Cache strategy (idêntica G4 — MD5 sobre hash de eventos):
 *  - Keyed por (since, events_hash)
 *  - MD5 dos eventos serializados (PRs+ADRs+Cycles+Files) — se nada mudou
 *    no intervalo, hash é igual e cache hit
 *  - tokens/cost rastreado pra dashboard Jana Pro
 *
 * Multi-tenant (ADR 0093):
 *  - SEM `business_id` — diffs são repo-wide (governança projeto inteiro)
 *  - Consistente com mcp_handoff_summaries + mcp_memory_documents
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_handoff_diffs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Parâmetro since serializado (ex: "1d", "3d", "2026-05-10")
            $table->string('since', 50)->index('idx_handoff_diff_since');

            // MD5 do JSON serializado dos eventos (PRs+ADRs+Cycles+Files)
            // Se nada mudou no intervalo, hash repete e cacheia
            $table->char('events_hash', 32);

            // Output renderizado em 2 formatos
            $table->longText('output_md')->nullable();
            $table->longText('output_json')->nullable();

            // Métricas custo (se usar LLM pra síntese narrativa — opcional)
            $table->unsignedInteger('tokens')->default(0);
            $table->decimal('cost_brl', 10, 6)->default(0)
                ->comment('Custo R$ acumulado (zero se síntese rule-based)');

            $table->timestamps();

            // Unique constraint: 1 row por (since, events_hash) — refaz se eventos mudaram
            $table->unique(['since', 'events_hash'], 'uniq_handoff_diff_since_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_handoff_diffs');
    }
};
