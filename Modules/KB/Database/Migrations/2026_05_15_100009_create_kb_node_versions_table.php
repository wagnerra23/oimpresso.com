<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration kb_node_versions — histórico append-only de edits em artigos.
 *
 * Contrato: memory/requisitos/KB/SCHEMA-DB-V1.md §8
 *
 * POPULADO APENAS pra kb_nodes.is_editable=true (artigos operacionais).
 * Bridges canônicos (ADR/session/charter) já têm versionamento via
 * mcp_memory_documents_history — sem dupla escrita.
 *
 * Append-only enforced em KbNodeVersionObserver (UPDATE e DELETE = exception).
 * Trigger MySQL fica V2 (V1 confia no Observer Eloquent — ADR 0061).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('kb_node_versions')) {
            return;
        }

        Schema::create('kb_node_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('node_id');
            $table->timestamp('version_at');
            $table->unsignedBigInteger('author_user_id')->nullable();
            $table->json('snapshot')
                ->comment('{title, excerpt, body_blocks, tags, status, category_id, subcategory_id, nivel, equip}');
            $table->string('change_reason', 255)->nullable();

            // Sem $table->timestamps() — version_at é a única timestamp relevante.

            $table->index(['business_id', 'node_id', 'version_at'], 'idx_kb_versions_biz_node_when');

            $table->foreign('business_id', 'fk_kb_versions_business')
                ->references('id')->on('business')->onDelete('cascade');

            $table->foreign('node_id', 'fk_kb_versions_node')
                ->references('id')->on('kb_nodes')->onDelete('cascade');

            $table->foreign('author_user_id', 'fk_kb_versions_author')
                ->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_node_versions');
    }
};
