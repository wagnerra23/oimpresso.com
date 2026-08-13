<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Substitui mcp_tasks.blocked_by JSON por estrutura relacional.
 * Permite tipos diferentes de relação: blocks, relates, duplicates, clones.
 *
 * blocked_by JSON em mcp_tasks fica como cache até backfill completo.
 */
class CreateMcpTaskDependenciesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_task_dependencies', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('task_id', 40)
                ->comment('Task que tem a dependência');

            $table->string('depends_on_task_id', 40)
                ->comment('Task da qual depende');

            $table->enum('type', ['blocks', 'relates', 'duplicates', 'clones'])
                ->default('blocks');

            $table->string('created_by', 60)->nullable();

            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id', 'type'], 'uq_mcp_task_dep_pair');
            $table->index('task_id', 'idx_mcp_task_dep_task');
            $table->index('depends_on_task_id', 'idx_mcp_task_dep_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_task_dependencies');
    }
}
