<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de custo do HandoffDraftTool — fecha a tabela FANTASMA.
 *
 * O código já escrevia aqui desde sempre: `HandoffDraftTool::trackCusto()`
 * (`Modules/Jana/Mcp/Tools/HandoffDraftTool.php`) faz
 * `DB::table('mcp_handoff_drafts')->insert([...])` dentro de try/catch, e o
 * catch só logava `Log::debug('tabela mcp_handoff_drafts ausente')`. Como
 * migration nenhuma criava a tabela, o insert falhava SEMPRE em produção e o
 * custo de gerar rascunho de handoff NUNCA era persistido — o tracking existia
 * no papel, não no banco. Levantado na higiene de schema da camada de IA
 * (2026-07-28); verificado: 0 ocorrências de `CREATE TABLE mcp_handoff_drafts`
 * em `database/schema/mysql-schema.sql`, e o único lugar que criava a tabela
 * era o próprio `HandoffDraftToolTest` (em memória).
 *
 * Schema espelha as duas tabelas IRMÃS do mesmo fluxo, que já existem e já
 * registram custo do jeito certo:
 *  - `mcp_handoff_summaries` (2026_05_13_120000) — cache + custo do resumo
 *  - `mcp_handoff_diffs`     (2026_05_13_130000) — cache + custo do diff
 * As colunas são exatamente as que `HandoffDraftToolTest` já declara, para o
 * teste passar a exercitar o schema real em vez de um stub divergente.
 *
 * Diferença vs as irmãs (deliberada): draft NÃO é cache.
 *  - sem `content_hash` / sem unique composto — cada geração é um evento novo,
 *    não uma entrada de cache deduplicável por conteúdo;
 *  - sem `tokens_in/out` — o tool só apura o custo consolidado (`cost_brl`).
 *
 * Multi-tenant ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)):
 *  - SEM `business_id` — handoffs são repo-wide (governança do projeto inteiro,
 *    não business-specific). Consistente com `mcp_handoff_summaries`/`_diffs` e
 *    com `mcp_memory_documents`.
 *  - Postura formalizada em [ADR 0280](../../../../memory/decisions/0280-postura-multi-tenant-tabelas-mcp-governanca.md):
 *    governança de plataforma é repo-wide by-design — ausência de `business_id`
 *    aqui é decisão registrada, NÃO vazamento cross-tenant.
 *
 * Idempotente: `hasTable` na subida e `dropIfExists` na descida — sobrevive a
 * re-run e a `migrate:fresh` sobre banco que já tenha a tabela (ex.: criada
 * antes por um teste que não limpou).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mcp_handoff_drafts')) {
            return;
        }

        Schema::create('mcp_handoff_drafts', function (Blueprint $table) {
            $table->bigIncrements('id');

            // filename do draft gerado em memory/handoffs/ — ex `2026-07-28-1400-higiene-schema.md`
            $table->string('filename', 200)->index('idx_handoff_draft_filename');

            // Custo R$ da geração do skeleton (decimal — nunca float, evita drift de arredondamento)
            $table->decimal('cost_brl', 10, 6)->default(0)
                ->comment('Custo R$ da geração deste draft');

            // Modelo usado — rastreia se vale re-gerar após upgrade de modelo
            $table->string('model', 50)->default('gpt-4o-mini');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_handoff_drafts');
    }
};
