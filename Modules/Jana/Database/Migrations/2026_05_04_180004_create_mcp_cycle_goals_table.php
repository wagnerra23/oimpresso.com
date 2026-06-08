<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0070 — Jira-style task management.
 *
 * Cycle goal = métrica trackável durante o cycle (ex: "Larissa responde 3 perguntas
 * de faturamento corretamente"). Cada cycle pode ter 1-N goals; um cycle só "fecha
 * com sucesso" se todos os goals atingidos.
 */
class CreateMcpCycleGoalsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_cycle_goals', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('cycle_id');

            $table->text('description');

            $table->string('metric_name', 80)->nullable()
                ->comment('Slug da métrica trackable (ex: memoria_recall_chars)');

            $table->string('target_value', 80)->nullable()
                ->comment('Valor alvo em string (suporta números, "true", "yes", etc)');

            $table->string('achieved_value', 80)->nullable()
                ->comment('Valor atual/atingido — atualizado via cycle-goals-track');

            $table->enum('status', ['open', 'done', 'missed'])->default('open');

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['cycle_id', 'status'], 'idx_mcp_cycle_goals_cycle_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_cycle_goals');
    }
}
