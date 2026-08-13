<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Epic = agrupamento de stories sob um tema/iniciativa do projeto.
 * Tasks pertencem a no máximo 1 epic; epic pode estar em vários cycles.
 */
class CreateMcpEpicsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_epics', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('project_id');
            $table->string('key', 24)
                ->comment('Ex: COPI-EP-001 — único dentro do projeto');

            $table->string('title', 200);
            $table->text('description')->nullable();

            $table->string('owner', 60)->nullable();
            $table->string('target_quarter', 16)->nullable()
                ->comment('Ex: Q2-2026, Q3-2026');

            $table->enum('status', ['planning', 'active', 'done', 'cancelled'])
                ->default('planning');

            $table->string('color', 16)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'key'], 'uq_mcp_epics_project_key');
            $table->index(['project_id', 'status'], 'idx_mcp_epics_project_status');
            $table->index('owner', 'idx_mcp_epics_owner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_epics');
    }
}
