<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 §5 (G8 P2 quick win) — Weekly digest.
 *
 * Reflect.app tem weekly review com AI digest. Wagner não vê "o que mudou na semana"
 * em 1 lugar. Esta tabela persiste o digest gerado por `jana:weekly-digest` (segunda
 * 09:00 BRT) pra tool MCP `weekly-digest-fetch` consumir sem reler arquivo do disco
 * + permitir métricas de cobertura/custo histórico.
 *
 * Filename arquivo: `memory/sessions/WEEKLY-DIGEST-YYYY-Www.md` (NÃO colide com
 * `SEMANA-YYYY-Www-resumo.md` do `copiloto:sintese-semanal` — ambos coexistem,
 * digest = report estruturado (Reflect-style), sintese = narrativa LLM).
 *
 * Multi-tenant (ADR 0093):
 *  - SEM `business_id` — digest é repo-wide (governança projeto inteiro, não
 *    business-specific). Consistente com `mcp_handoff_summaries`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_weekly_digests', function (Blueprint $table) {
            $table->bigIncrements('id');

            // ISO week (YYYY-Www) — ex `2026-W19`. Unique constraint garante idempotência.
            $table->string('week', 8)->unique('uniq_weekly_digest_week');

            // Range temporal (segunda 00:00 → domingo 23:59 ISO)
            $table->date('range_start');
            $table->date('range_end');

            // Markdown gerado (5 seções: Marco / Trabalho / Cycle progress /
            // Decisões / Próxima semana). Persistido também em disco pra leitura
            // direta (memory/sessions/WEEKLY-DIGEST-*.md).
            $table->longText('digest_markdown');

            // Métricas coletadas (JSON snapshot dos contadores brutos pra audit)
            $table->json('metrics')->nullable()
                ->comment('JSON: {commits, prs_merged, us_closed, adrs_new, handoffs, cycle_progress_pct}');

            // Custo gpt-4o-mini (pra dashboard Jana Pro)
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_brl', 10, 6)->default(0)
                ->comment('Custo R$ deste digest (gpt-4o-mini)');

            $table->string('model', 50)->default('gpt-4o-mini');

            $table->timestamps();

            $table->index('range_end', 'idx_weekly_digest_range_end');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_weekly_digests');
    }
};
