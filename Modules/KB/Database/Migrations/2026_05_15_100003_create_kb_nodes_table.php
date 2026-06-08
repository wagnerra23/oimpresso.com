<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_nodes — nó do grafo de conhecimento.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §3
 *
 * Invariante CRÍTICA Tier 0 (ADR 0093 + ADR 0061):
 *   is_editable=false  ⇒  body_blocks IS NULL
 *   is_editable=false  ⇒  conteúdo vem do JOIN com mcp_memory_documents.content_md
 *
 * Enforcement em runtime via KbNodeObserver (saving event). MySQL CHECK constraint
 * não é suficiente porque body_blocks é JSON e validação parcial.
 *
 * Bridge canônico (ADRs/sessions/charters/runbooks/briefings/specs):
 *   - source_doc_id → FK mcp_memory_documents.id (nullable SET NULL)
 *   - is_editable = false
 *   - body_blocks = NULL
 *
 * Bridge ERP (OS, cliente, NFe, equipamento):
 *   - source_entity_type = 'App\\Transaction' | 'App\\Contact' | etc
 *   - source_entity_id   = id da entity
 *
 * Artigo operacional (Larissa Cowork):
 *   - is_editable = true
 *   - body_blocks = JSON array of blocks
 *   - source_doc_id = NULL
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_nodes')) {
            return;
        }

        Schema::create('kb_nodes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');

            // Tipo do nó governa renderização + edição.
            $table->string('type', 40)
                ->comment('article|adr|session|charter|runbook|briefing|spec|comparativo|reference|os|customer|product|nfe|equipment|external_file');

            $table->string('slug', 180);
            $table->string('title', 255);
            $table->string('excerpt', 500)->nullable();

            // Conteúdo editável (apenas type=article ou externos). NULL pra bridge canon.
            $table->json('body_blocks')->nullable()
                ->comment('array de blocks [{kind: para|h2|list|callout|image, ...}]');

            // Bridge pra fotografia git canônica (read-only).
            $table->unsignedBigInteger('source_doc_id')->nullable()
                ->comment('FK mcp_memory_documents.id quando type in (adr|session|charter|...)');
            $table->string('source_entity_type', 80)->nullable()
                ->comment('App\\Transaction, App\\Contact, etc — quando type in (os|customer|nfe|...)');
            $table->unsignedBigInteger('source_entity_id')->nullable();

            // Governança / status.
            $table->boolean('is_editable')->default(false)
                ->comment('true só pra type=article (operacional). bridges = false.');
            $table->string('status', 40)->default('ok')
                ->comment('draft|ok|outdated|deleted|deprecated');
            $table->boolean('pinned')->default(false);

            // Taxonomia operacional (NULL pra bridges).
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('subcategory_id')->nullable();
            $table->string('nivel', 20)->nullable()
                ->comment('iniciante|intermediario|avancado (operacional)');
            $table->string('equip', 80)->nullable()
                ->comment('Roland VS-540, HP Latex 365, etc');
            $table->json('tags')->nullable()->comment('array de strings');

            // Métricas.
            $table->unsignedInteger('reads_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('outdated_votes')->default(0);
            $table->unsignedInteger('os_linked_count')->default(0);

            // Meta.
            // refs users.id (int unsigned, UltimatePOS legacy)
            $table->unsignedInteger('author_user_id')->nullable();
            $table->unsignedSmallInteger('read_time_min')->nullable();
            $table->timestamp('last_verified_at')->nullable()
                ->comment('última re-verificação pelo dono (botão "Re-verificar")');

            $table->timestamps();
            $table->softDeletes();

            // Índices (todos ≤64 chars).
            $table->unique(['business_id', 'slug'], 'uq_kb_nodes_business_slug');
            $table->index(['business_id', 'type'], 'idx_kb_nodes_business_type');
            $table->index(['business_id', 'status', 'pinned'], 'idx_kb_nodes_biz_status_pin');
            $table->index('source_doc_id', 'idx_kb_nodes_source_doc');
            $table->index(['source_entity_type', 'source_entity_id'], 'idx_kb_nodes_source_entity');
            $table->index('category_id', 'idx_kb_nodes_category');
            $table->index('subcategory_id', 'idx_kb_nodes_subcategory');
            $table->index('author_user_id', 'idx_kb_nodes_author');
            $table->index('deleted_at', 'idx_kb_nodes_deleted');

            // Foreign keys.
            $table->foreign('business_id', 'fk_kb_nodes_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('source_doc_id', 'fk_kb_nodes_source_doc')
                ->references('id')->on('mcp_memory_documents')->onDelete('set null');

            $table->foreign('category_id', 'fk_kb_nodes_category')
                ->references('id')->on('kb_categories')->onDelete('set null');

            $table->foreign('subcategory_id', 'fk_kb_nodes_subcategory')
                ->references('id')->on('kb_subcategories')->onDelete('set null');

            $table->foreign('author_user_id', 'fk_kb_nodes_author')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_nodes');
    }
};
