<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_edges — aresta tipada do grafo de conhecimento.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §4
 *
 * Tipos de aresta (governance + operational):
 *   - supersedes        → auto-derivada de frontmatter `supersedes:` de ADR
 *   - charter-of        → auto do path `*.charter.md` ao lado de .tsx
 *   - references-data   → auto do regex `#OS-1234`, `cliente XYZ` em body
 *   - cross-link        → auto do `#kb-XXX` em body_blocks
 *   - ai-related        → auto via cosine similarity de embeddings (ONDA 4)
 *   - related-by-tag    → auto via tag overlap + cat/equip bonus
 *   - next-in-path      → manual via editor de trilha
 *   - fix-of-decision   → manual via editor de troubleshooter
 *
 * CHECK constraint `from_node_id <> to_node_id` previne self-loop.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_edges')) {
            return;
        }

        Schema::create('kb_edges', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('from_node_id');
            $table->unsignedBigInteger('to_node_id');

            $table->string('edge_type', 40)
                ->comment('next-in-path|fix-of-decision|supersedes|charter-of|references-data|ai-related|cross-link|related-by-tag');

            $table->decimal('weight', 5, 3)->default(1.000)
                ->comment('pra ai-related/related-by-tag, score 0-1');
            $table->json('payload')->nullable()
                ->comment('metadata específico (ex: ai-related → embedding score; cross-link → block_idx)');

            $table->string('generated_by', 40)->default('manual')
                ->comment('manual|bridge_job|ai_embed|tag_overlap|user_action');

            $table->timestamps();

            // Índices (todos ≤64 chars).
            $table->unique(['business_id', 'from_node_id', 'to_node_id', 'edge_type'], 'uq_kb_edges_triple');
            $table->index(['business_id', 'from_node_id'], 'idx_kb_edges_biz_from');
            $table->index(['business_id', 'to_node_id'], 'idx_kb_edges_biz_to');
            $table->index(['business_id', 'edge_type'], 'idx_kb_edges_biz_type');

            // Foreign keys.
            $table->foreign('business_id', 'fk_kb_edges_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('from_node_id', 'fk_kb_edges_from')
                ->references('id')->on('kb_nodes')->onDelete('cascade');

            $table->foreign('to_node_id', 'fk_kb_edges_to')
                ->references('id')->on('kb_nodes')->onDelete('cascade');
        });

        // CHECK constraint pra prevenir self-loop. MySQL 8+ enforce.
        // TODO[CL]: confirmar versão MySQL prod (Hostinger) suporta CHECK enforced.
        try {
            \DB::statement('ALTER TABLE kb_edges ADD CONSTRAINT chk_kb_edges_no_self CHECK (from_node_id <> to_node_id)');
        } catch (\Throwable $e) {
            // MySQL <8.0.16 ignora CHECK silenciosamente. Observer reforça em PHP.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_edges');
    }
};
