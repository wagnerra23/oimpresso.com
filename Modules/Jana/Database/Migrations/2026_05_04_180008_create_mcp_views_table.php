<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Saved view = query salva (filter+sort) reutilizável.
 * Linear/Jira-style: "My active tasks", "Bloqueadas", "Triage", etc.
 *
 * scope:
 *   - personal: só o user que criou vê (user_id obrigatório)
 *   - shared:   todos do projeto vêem (project_id obrigatório)
 *   - system:   default sistema, não-editável (ex: triage, my-work)
 */
class CreateMcpViewsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_views', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->enum('scope', ['personal', 'shared', 'system'])
                ->default('personal');

            $table->json('filter')
                ->comment('{module,status,owner,priority,labels,...} estrutura interpretada por TasksListTool');

            $table->string('sort', 60)->default('-due_date')
                ->comment('Campo de sort com prefixo + ou - (ex: -priority,due_date)');

            $table->boolean('is_pinned')->default(false);

            $table->timestamps();

            $table->index('user_id', 'idx_mcp_views_user');
            $table->index('project_id', 'idx_mcp_views_project');
            $table->index('scope', 'idx_mcp_views_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_views');
    }
}
