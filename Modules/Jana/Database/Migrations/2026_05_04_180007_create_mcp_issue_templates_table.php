<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Issue template = body markdown pré-preenchido + default fields.
 * Aplicado via tasks-create --template=bug.
 */
class CreateMcpIssueTemplatesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_issue_templates', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('project_id')->nullable()
                ->comment('NULL = template global (todos projetos)');

            $table->enum('type', ['story', 'task', 'bug', 'spike', 'chore'])
                ->default('task');

            $table->string('name', 80);
            $table->text('body_template')
                ->comment('Markdown pré-preenchido (suporta ## Steps to reproduce, etc)');

            $table->json('default_fields')->nullable()
                ->comment('{priority,labels,estimate_unit,estimate_value,custom_fields}');

            $table->timestamps();

            $table->unique(['project_id', 'type', 'name'], 'uq_mcp_issue_templates_scope_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_issue_templates');
    }
}
