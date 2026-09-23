<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-09-23 — De-para explícito conta → linha da DRE.
 *
 * A DRE (DreService) classificava só pelo PREFIXO do código (`3.1.01.` receita,
 * `3.1.02.` deduções, `4.` custos, `5.` despesas — padrão do PlanoContasBrSeeder).
 * Business com plano próprio (ex.: o legado migrado do sistema antigo, `1.x`
 * entradas / `2.x` saídas) nunca casava e a DRE vinha zerada com o aviso
 * "categorias não mapeadas". Decisão [W] 2026-09-23: de-para por conta, sem mexer
 * nos títulos.
 *
 * Valores: receita_bruta | deducoes | custos | despesas | fora (conta que não entra
 * na DRE). NULL = segue o prefixo, exatamente como antes — aditivo, sem backfill.
 */
class AddDreLinhaToFinPlanosConta extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fin_planos_conta', 'dre_linha')) {
            return;
        }

        Schema::table('fin_planos_conta', function (Blueprint $table) {
            $table->string('dre_linha', 20)->nullable()->after('natureza');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('fin_planos_conta', 'dre_linha')) {
            return;
        }

        Schema::table('fin_planos_conta', function (Blueprint $table) {
            $table->dropColumn('dre_linha');
        });
    }
}
