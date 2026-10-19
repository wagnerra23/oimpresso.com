<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-FLX-33 · US-SELL-067 — custo previsto da linha de orçamento, congelado no dia.
 *
 * Três colunas nulas em `transaction_sell_lines`: o custo do insumo na data do orçamento,
 * a data e o método usado (CustoDoInsumoService). Linhas existentes ficam nulas: não há
 * backfill, porque o custo do dia de um orçamento antigo não se reconstrói com segurança
 * (a empresa pode ter trocado de método). Não mexe em preço, total nem estoque.
 *
 * Idempotente: só cria a coluna que faltar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_sell_lines', function (Blueprint $t) {
            if (! Schema::hasColumn('transaction_sell_lines', 'custo_previsto')) {
                $t->decimal('custo_previsto', 22, 4)->nullable();
            }
            if (! Schema::hasColumn('transaction_sell_lines', 'custo_previsto_em')) {
                $t->date('custo_previsto_em')->nullable();
            }
            if (! Schema::hasColumn('transaction_sell_lines', 'custo_previsto_metodo')) {
                $t->string('custo_previsto_metodo', 12)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaction_sell_lines', function (Blueprint $t) {
            foreach (['custo_previsto', 'custo_previsto_em', 'custo_previsto_metodo'] as $coluna) {
                if (Schema::hasColumn('transaction_sell_lines', $coluna)) {
                    $t->dropColumn($coluna);
                }
            }
        });
    }
};
