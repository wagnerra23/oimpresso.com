<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Workflow = statuses + transitions customizáveis por projeto.
 * Default global: backlog→todo→doing→review→done (+blocked/cancelled lateral).
 *
 * statuses JSON: [{key,name,category,color}]
 * transitions JSON: {"todo": ["doing","cancelled"], "doing": ["review","blocked"], ...}
 */
class CreateMcpWorkflowsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_workflows', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('project_id')->nullable()
                ->comment('NULL = workflow global (default fallback)');

            $table->string('name', 100);

            $table->json('statuses')
                ->comment('[{key,name,category:todo|in-progress|done,color}]');

            $table->json('transitions')
                ->comment('{"<from>": ["<to>", "<to>"], ...}');

            $table->boolean('is_default')->default(false)
                ->comment('Workflow default do projeto (1 por projeto)');

            $table->timestamps();

            $table->index('project_id', 'idx_mcp_workflows_project');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_workflows');
    }
}
