<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * D1 Diferencial oimpresso-only: task ↔ memory document (ADR/SPEC/session/comparativo).
 * Linear/Jira NÃO têm — viabilizado por nosso MCP server unificar tudo.
 *
 * Auto-populado por:
 *   - parser quando SPEC menciona "ver ADR 0035"
 *   - tool MCP tasks-link-memory (manual)
 *   - AI tool tasks-suggest-memory (D2)
 */
class CreateMcpTaskMemoryLinksTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_task_memory_links', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('task_id', 40);
            $table->unsignedBigInteger('memory_document_id')
                ->comment('FK lógico mcp_memory_documents.id');

            $table->enum('link_type', ['relates', 'spec', 'adr', 'session', 'comparativo', 'runbook'])
                ->default('relates');

            $table->string('created_by', 60)->nullable()
                ->comment('Username ou "ai-suggest" se gerado por D2');

            $table->boolean('confirmed')->default(false)
                ->comment('Se ai-suggest, precisa confirmação humana antes de exibir como canônico');

            $table->timestamps();

            $table->unique(['task_id', 'memory_document_id', 'link_type'], 'uq_mcp_task_memory_links');
            $table->index('task_id', 'idx_mcp_task_memory_links_task');
            $table->index('memory_document_id', 'idx_mcp_task_memory_links_doc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_task_memory_links');
    }
}
