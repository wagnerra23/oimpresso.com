<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-KB-3 / F1 (ADR 0053 → 0059) — Colunas tipadas em mcp_memory_documents
 * pra absorver o frontmatter YAML obrigatório das ADRs.
 *
 * Schema canônico: memory/decisions/_SCHEMA.md
 * Validador formal: memory/decisions/_schema.json
 *
 * Antes: tudo morava em metadata JSON (string solta sem filtro queryable).
 * Agora: campos governance (status/authority/lifecycle/quarter/decided_at)
 * viram colunas indexadas, listas (supersedes/superseded_by/related/tags) ficam
 * em JSON column dedicada (FK frouxo, fácil de buscar com whereJsonContains).
 *
 * Tools MCP destravadas:
 *   - decisions-search status:aceito module:copiloto authority:canonical
 *   - decisions-fetch retorna warning se status=superseded
 *   - KB UI ganha filtros queryable no sidebar
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mcp_memory_documents', function (Blueprint $table) {
            // Governance — indexáveis
            $table->string('status', 20)->nullable()->after('module')
                ->comment('rascunho|proposto|aceito|deprecated|superseded — frontmatter status');
            $table->string('authority', 20)->nullable()->after('status')
                ->comment('canonical|reference|exploratory — peso da fonte');
            $table->string('lifecycle', 20)->nullable()->after('authority')
                ->comment('ativo|arquivado|substituido');
            $table->string('quarter', 10)->nullable()->after('lifecycle')
                ->comment('YYYY-Qn — ex: 2026-Q2');
            $table->date('decided_at')->nullable()->after('quarter')
                ->comment('Data da decisão (frontmatter decided_at)');

            // Campos JSON — listas curtas, busca via whereJsonContains
            $table->json('decided_by')->nullable()->after('decided_at')
                ->comment('Iniciais do TEAM.md, ex: ["W", "F"]');
            $table->json('tags')->nullable()->after('decided_by')
                ->comment('Tags semânticas, ex: ["mcp", "lgpd"]');
            $table->json('supersedes')->nullable()->after('tags')
                ->comment('Slugs de ADRs substituídas (full + partial mergeados)');
            $table->json('superseded_by')->nullable()->after('supersedes')
                ->comment('Slugs de ADRs que substituíram esta');
            $table->json('related')->nullable()->after('superseded_by')
                ->comment('Slugs de ADRs relacionadas (não-substituidoras)');

            // Flag PII (boolean tipado vs metadata JSON)
            $table->boolean('has_pii')->default(false)->after('related')
                ->comment('true se corpo contém PII redactada');

            // Indexes pra filtros frequentes
            $table->index('status', 'mcp_md_status_idx');
            $table->index('authority', 'mcp_md_authority_idx');
            $table->index('lifecycle', 'mcp_md_lifecycle_idx');
            $table->index('quarter', 'mcp_md_quarter_idx');
            $table->index('decided_at', 'mcp_md_decided_at_idx');
            $table->index(['type', 'status', 'lifecycle'], 'mcp_md_type_status_life_idx');
        });
    }

    public function down(): void
    {
        Schema::table('mcp_memory_documents', function (Blueprint $table) {
            $table->dropIndex('mcp_md_type_status_life_idx');
            $table->dropIndex('mcp_md_decided_at_idx');
            $table->dropIndex('mcp_md_quarter_idx');
            $table->dropIndex('mcp_md_lifecycle_idx');
            $table->dropIndex('mcp_md_authority_idx');
            $table->dropIndex('mcp_md_status_idx');

            $table->dropColumn([
                'status',
                'authority',
                'lifecycle',
                'quarter',
                'decided_at',
                'decided_by',
                'tags',
                'supersedes',
                'superseded_by',
                'related',
                'has_pii',
            ]);
        });
    }
};
