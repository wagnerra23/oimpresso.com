<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-FLX-33 · US-COMVIS-019 — custo previsto do item do orçamento, congelado no dia.
 *
 * O cadastro do material (`comvis_materiais.preco_custo_m2`) não tem histórico: guardar o
 * valor no item é o único jeito de saber, depois, com que custo o orçamento foi feito.
 * Colunas nulas, sem backfill: o custo de um orçamento antigo não se reconstrói.
 * Não mexe em preço, subtotal nem total do orçamento.
 *
 * Idempotente: só cria a coluna que faltar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comvis_orcamento_itens', function (Blueprint $t) {
            if (! Schema::hasColumn('comvis_orcamento_itens', 'custo_previsto_m2')) {
                $t->decimal('custo_previsto_m2', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('comvis_orcamento_itens', 'custo_previsto')) {
                $t->decimal('custo_previsto', 15, 2)->nullable();
            }
            if (! Schema::hasColumn('comvis_orcamento_itens', 'custo_previsto_em')) {
                $t->date('custo_previsto_em')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('comvis_orcamento_itens', function (Blueprint $t) {
            foreach (['custo_previsto_m2', 'custo_previsto', 'custo_previsto_em'] as $coluna) {
                if (Schema::hasColumn('comvis_orcamento_itens', $coluna)) {
                    $t->dropColumn($coluna);
                }
            }
        });
    }
};
