<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TaskRegistry Fase 1 (US-TR-006) — audit log de eventos por task.
 *
 * Registra: criação, mudança de status, reatribuição, comentário, cancelamento.
 * Append-only — nunca UPDATE/DELETE nesta tabela.
 */
class CreateMcpTaskEventsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_task_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('task_id', 40)
                ->comment('US-NNN-MMM — ref à mcp_tasks.task_id');

            $table->enum('event_type', [
                'created',         // task apareceu no sync pela primeira vez
                'status_changed',  // status mudou (ex: todo → doing)
                'assigned',        // owner mudou
                'field_updated',   // sprint/priority/estimate atualizados
                'commented',       // comentário adicionado
                'cancelled',       // US sumiu do SPEC → status=cancelled
            ])->comment('Tipo do evento para filtro/timeline');

            $table->string('from_value', 255)->nullable()
                ->comment('Valor anterior (ex: "todo", "wagner")');

            $table->string('to_value', 255)->nullable()
                ->comment('Valor novo (ex: "doing", "eliana")');

            $table->string('author', 60)->nullable()
                ->comment('Quem causou o evento (username ou "system")');

            $table->text('note')->nullable()
                ->comment('Descrição livre (ex: campo atualizado, motivo)');

            $table->timestamp('occurred_at')->useCurrent()
                ->comment('Quando ocorreu — imutável');

            // Sem updated_at: tabela append-only
            $table->timestamp('created_at')->nullable();

            $table->index(['task_id', 'occurred_at'], 'idx_mcp_task_events_task_timeline');
            $table->index('event_type', 'idx_mcp_task_events_type');
            $table->index('author', 'idx_mcp_task_events_author');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_task_events');
    }
}
