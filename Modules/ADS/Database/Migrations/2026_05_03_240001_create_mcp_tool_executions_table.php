<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log de execuções de Tools — toda invocação de tool grava aqui.
 * Crítico para ferramentas de ESCRITA (rastreabilidade LGPD + governança).
 */
class CreateMcpToolExecutionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_tool_executions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id')->default(1);
            $table->unsignedBigInteger('decision_id')->nullable();   // se foi disparada por decision

            $table->string('tool_name', 80);
            $table->boolean('is_read_only');
            $table->json('input');                                   // sanitizado (sem secrets)
            $table->boolean('ok');
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->string('triggered_by', 50);                      // 'wagner'|'brain_b'|'scheduler'
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tool_name', 'created_at'], 'idx_tool_exec_name_time');
            $table->index('decision_id', 'idx_tool_exec_decision');
            $table->index('ok', 'idx_tool_exec_ok');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_executions');
    }
}
