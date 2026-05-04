<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Project = unidade superior de agrupamento. 1 project ≈ 1 módulo
 * (Copiloto, NFSe, Financeiro, etc) ou área transversal (Infra).
 *
 * Tasks têm identifier humano: "<PROJECT_KEY>-<NNNN>" (Linear-style).
 * next_task_number é contador atômico por projeto.
 *
 * NOTA — nome da tabela: mcp_jira_projects (não mcp_projects).
 * mcp_projects já está ocupado pelo Modules/ADS Decision Engine de Wagner
 * (commit a7e6da54, schema viability/decision/proceed-pivot-kill).
 * Prefixo mcp_jira_* deixa namespace claro e evita conflito de classe PHP.
 */
class CreateMcpJiraProjectsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_jira_projects', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('key', 16)->unique()
                ->comment('Linear-style: COPI, NFSE, FIN. Maiúsculo, sem espaço.');

            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->unsignedBigInteger('lead_user_id')->nullable()
                ->comment('Owner do projeto (FK lógico pra users.id, sem FK física)');

            $table->string('color', 16)->nullable()
                ->comment('Hex ou nome Tailwind para UI');

            $table->string('icon', 32)->nullable()
                ->comment('Lucide icon name');

            $table->enum('status', ['active', 'archived'])->default('active');

            $table->json('settings')->nullable()
                ->comment('Settings por projeto: default_workflow_id, default_estimate_unit, etc');

            $table->unsignedBigInteger('default_workflow_id')->nullable()
                ->comment('FK lógico mcp_workflows.id — workflow default das tasks deste projeto');

            $table->json('custom_field_schema')->nullable()
                ->comment('Schema dos custom fields aceitos: [{key,type,label,options}]');

            $table->unsignedInteger('next_task_number')->default(1)
                ->comment('Contador atômico — próximo número de identifier');

            $table->timestamps();
            $table->softDeletes();

            $table->index('status', 'idx_mcp_jira_projects_status');
            $table->index('lead_user_id', 'idx_mcp_jira_projects_lead');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_jira_projects');
    }
}
