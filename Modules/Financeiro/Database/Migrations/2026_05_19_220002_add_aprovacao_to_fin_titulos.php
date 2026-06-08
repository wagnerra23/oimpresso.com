<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 21 (2026-05-19) #55 — Workflow aprovação de pagamento (FSM básico).
 *
 * Adiciona campos pra fluxo: Eliana cria → Wagner aprova → pagamento liberado.
 *
 * Estados aprovacao_status:
 *   - null (default — sem fluxo de aprovação, comportamento atual)
 *   - 'pendente'   — aguardando aprovação
 *   - 'aprovado'   — liberado pra pagar
 *   - 'rejeitado'  — bloqueado pra pagar (com motivo)
 *
 * Backward compat: títulos antigos têm aprovacao_status = NULL e seguem
 * fluxo direto (sem aprovação). UI opt-in liga quando business habilita.
 */
class AddAprovacaoToFinTitulos extends Migration
{
    public function up(): void
    {
        Schema::table('fin_titulos', function (Blueprint $table) {
            $table->enum('aprovacao_status', ['pendente', 'aprovado', 'rejeitado'])->nullable()->after('status');
            $table->integer('aprovado_by')->unsigned()->nullable()->after('aprovacao_status');
            $table->timestamp('aprovado_at')->nullable()->after('aprovado_by');
            $table->string('aprovacao_motivo', 500)->nullable()->after('aprovado_at');

            $table->index(['business_id', 'aprovacao_status']);
        });
    }

    public function down(): void
    {
        Schema::table('fin_titulos', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'aprovacao_status']);
            $table->dropColumn(['aprovacao_status', 'aprovado_by', 'aprovado_at', 'aprovacao_motivo']);
        });
    }
}
