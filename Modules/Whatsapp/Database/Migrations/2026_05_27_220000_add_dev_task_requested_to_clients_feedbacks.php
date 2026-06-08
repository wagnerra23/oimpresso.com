<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * clients_feedbacks — adiciona dev_task_requested boolean
 *
 * Wagner 2026-05-27: atendente conseguir marcar feedback como "vira chamado de dev"
 * direto da conversa WhatsApp. Triagem Wagner cria task MCP via tool em até 24h.
 *
 * Refs: ADR 0105 (cliente-como-sinal), feedback-management RUNBOOK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients_feedbacks')) {
            return;
        }
        if (Schema::hasColumn('clients_feedbacks', 'dev_task_requested')) {
            return;
        }

        Schema::table('clients_feedbacks', function (Blueprint $table) {
            $table->boolean('dev_task_requested')->default(false)->after('mcp_task_id');
            $table->index(['business_id', 'dev_task_requested'], 'idx_biz_dev_task_pending');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clients_feedbacks', 'dev_task_requested')) {
            return;
        }
        Schema::table('clients_feedbacks', function (Blueprint $table) {
            $table->dropIndex('idx_biz_dev_task_pending');
            $table->dropColumn('dev_task_requested');
        });
    }
};
