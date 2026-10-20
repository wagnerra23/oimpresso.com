<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-FLX-33 · US-COMVIS-019 — custo previsto da hora de máquina do item, congelado no dia.
 *
 * O posto do PCP (`pcp_postos.custo_hora`) não tem histórico: guardar o custo da hora e o custo
 * da máquina no item é o único jeito de saber, depois, com que custo o orçamento foi feito.
 * Colunas nulas, sem backfill: o custo de um orçamento antigo não se reconstrói.
 * Não mexe em preço, subtotal nem total do orçamento.
 *
 * `posto_id` sem FK: a CV não depende do módulo Producao (o custo chega pela interface
 * App\Contracts\Producao\CustoHoraDoPosto).
 *
 * Idempotente: só cria a coluna que faltar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comvis_orcamento_itens', function (Blueprint $t) {
            if (! Schema::hasColumn('comvis_orcamento_itens', 'posto_id')) {
                $t->unsignedBigInteger('posto_id')->nullable()->index('comvis_orc_itens_posto_idx');
            }
            if (! Schema::hasColumn('comvis_orcamento_itens', 'horas_maquina_previstas')) {
                $t->decimal('horas_maquina_previstas', 8, 2)->nullable();
            }
            if (! Schema::hasColumn('comvis_orcamento_itens', 'custo_hora_previsto')) {
                $t->decimal('custo_hora_previsto', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('comvis_orcamento_itens', 'custo_maquina_previsto')) {
                $t->decimal('custo_maquina_previsto', 15, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('comvis_orcamento_itens', function (Blueprint $t) {
            if (Schema::hasColumn('comvis_orcamento_itens', 'posto_id')) {
                $t->dropIndex('comvis_orc_itens_posto_idx');
            }
            foreach (['posto_id', 'horas_maquina_previstas', 'custo_hora_previsto', 'custo_maquina_previsto'] as $coluna) {
                if (Schema::hasColumn('comvis_orcamento_itens', $coluna)) {
                    $t->dropColumn($coluna);
                }
            }
        });
    }
};
