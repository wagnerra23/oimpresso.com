<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Component = agrupamento ortogonal a Epic, ex: "Frontend"/"Infra"/"Memória"/"Tests".
 * Útil pra cross-cut: epic Memory pode ter components Frontend+Backend+Infra.
 */
class CreateMcpComponentsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_components', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('project_id');
            $table->string('key', 24)
                ->comment('Ex: FE, BE, INFRA, MEM — único dentro do projeto');

            $table->string('name', 100);
            $table->text('description')->nullable();

            $table->unsignedBigInteger('lead_user_id')->nullable();
            $table->string('color', 16)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'key'], 'uq_mcp_components_project_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_components');
    }
}
