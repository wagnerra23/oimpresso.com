<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Cycle = sprint de duração fixa (default 2 semanas) com goal outcome-oriented.
 * Substitui CURRENT.md (removido). Cycle ativo = 1 por projeto.
 */
class CreateMcpCyclesTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_cycles', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('project_id');
            $table->string('key', 24)
                ->comment('Ex: CYCLE-01, CYCLE-02 — único dentro do projeto');

            $table->string('name', 100)->nullable()
                ->comment('Label descritivo opcional');

            $table->date('start_date');
            $table->date('end_date');

            $table->text('goal')->nullable()
                ->comment('Outcome-oriented goal do cycle (1-2 frases)');

            $table->enum('status', ['planning', 'active', 'closed'])
                ->default('planning');

            $table->json('retro')->nullable()
                ->comment('Retro JSON: {sucessos:[...], falhas:[...], licao_prox:""}');

            $table->unsignedBigInteger('owner_user_id')->nullable()
                ->comment('FK lógico users.id — quem fechou/conduziu o cycle');

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'key'], 'uq_mcp_cycles_project_key');
            $table->index(['project_id', 'status'], 'idx_mcp_cycles_project_status');
            $table->index(['start_date', 'end_date'], 'idx_mcp_cycles_dates');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_cycles');
    }
}
