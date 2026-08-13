<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MEM-MCP-1.a (ADR 0053) — History append-only de mcp_memory_documents.
 *
 * Cada UPDATE em mcp_memory_documents move a versão anterior pra cá.
 * Permite responder: "Qual era ADR 0046 ANTES do MEM-FAT-1?" via SELECT
 * por git_sha ou data.
 *
 * Tabela IMUTÁVEL (sem updated_at, sem softDelete) — append-only.
 */
class CreateMcpMemoryDocumentsHistoryTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_memory_documents_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('document_id');
            $table->string('slug', 200);
            $table->string('git_sha', 40)->nullable();
            $table->string('title', 250);
            $table->mediumText('content_md');
            $table->json('metadata')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->unsignedInteger('changed_by_user_id')->nullable()
                ->comment('User que fez o git push (null se via CI/cron sync)');
            $table->string('change_reason', 100)->nullable()
                ->comment('webhook | manual | cron_fallback');
            $table->timestamp('created_at')->useCurrent();
            // Sem updated_at — append-only

            $table->index(['document_id', 'changed_at'], 'mcp_mh_doc_changed_idx');
            $table->index(['slug', 'changed_at'], 'mcp_mh_slug_changed_idx');
            $table->index('git_sha', 'mcp_mh_sha_idx');

            $table->foreign('document_id')->references('id')->on('mcp_memory_documents')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_memory_documents_history');
    }
}
