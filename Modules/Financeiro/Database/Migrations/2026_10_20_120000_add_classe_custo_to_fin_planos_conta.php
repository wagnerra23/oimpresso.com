<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-10-20 — Classe de custo da conta: fixa ou variável (US-FIN-070 · UC-FLX-20).
 *
 * O custeio RKW precisa saber quais despesas são FIXAS (rateadas pelos postos) e quais são
 * VARIÁVEIS (entram na margem de contribuição). Nada no plano de contas dizia isso: `natureza`
 * é débito/crédito e `dre_linha` agrupa para a DRE — "despesas" mistura aluguel (fixa) com
 * comissão (variável). Derivar dali seria inventar.
 *
 * NULL = a conta não entra no custeio, até alguém classificar. Sem backfill (aceite da US).
 * Nome `classe_custo` porque `natureza` já existe nesta tabela com outro sentido.
 */
class AddClasseCustoToFinPlanosConta extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fin_planos_conta', 'classe_custo')) {
            return;
        }

        Schema::table('fin_planos_conta', function (Blueprint $table) {
            $table->enum('classe_custo', ['fixa', 'variavel'])->nullable()->after('dre_linha');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fin_planos_conta', 'classe_custo')) {
            return;
        }

        Schema::table('fin_planos_conta', function (Blueprint $table) {
            $table->dropColumn('classe_custo');
        });
    }
}
