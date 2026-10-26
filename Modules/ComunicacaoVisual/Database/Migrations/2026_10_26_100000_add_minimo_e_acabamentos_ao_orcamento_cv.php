<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-COMVIS-001 — mínimo cobrado por peça e acabamentos no orçamento da CV.
 *
 * - `comvis_materiais.minimo_m2`: área mínima cobrada por peça (ex.: 0,5 m² mesmo se a peça é
 *   menor). Nula = sem mínimo. Não confundir com `estoque_minimo_m2`, que é estoque.
 * - `comvis_orcamento_itens.area_cobrada_m2`: área que o cliente paga (≥ `area_m2`, que segue
 *   sendo a área física e a base do custo). `valor_acabamentos` e `acabamentos_json` guardam o
 *   acabamento escolhido e o preço dele no dia (o catálogo não tem histórico).
 * - `comvis_orcamentos.acabamentos`: soma dos acabamentos, que entra no total.
 *
 * Sem backfill: orçamento antigo não tinha mínimo nem acabamento, então segue com o mesmo total
 * (área cobrada nula = área física; acabamentos 0). Idempotente: só cria a coluna que faltar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comvis_materiais') && ! Schema::hasColumn('comvis_materiais', 'minimo_m2')) {
            Schema::table('comvis_materiais', function (Blueprint $t) {
                $t->decimal('minimo_m2', 8, 3)->nullable();
            });
        }

        Schema::table('comvis_orcamento_itens', function (Blueprint $t) {
            if (! Schema::hasColumn('comvis_orcamento_itens', 'area_cobrada_m2')) {
                $t->decimal('area_cobrada_m2', 10, 3)->nullable();
            }
            if (! Schema::hasColumn('comvis_orcamento_itens', 'valor_acabamentos')) {
                $t->decimal('valor_acabamentos', 15, 2)->default(0);
            }
            if (! Schema::hasColumn('comvis_orcamento_itens', 'acabamentos_json')) {
                $t->json('acabamentos_json')->nullable();
            }
        });

        if (! Schema::hasColumn('comvis_orcamentos', 'acabamentos')) {
            Schema::table('comvis_orcamentos', function (Blueprint $t) {
                $t->decimal('acabamentos', 15, 2)->default(0);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('comvis_orcamentos', 'acabamentos')) {
            Schema::table('comvis_orcamentos', fn (Blueprint $t) => $t->dropColumn('acabamentos'));
        }
        foreach (['area_cobrada_m2', 'valor_acabamentos', 'acabamentos_json'] as $coluna) {
            if (Schema::hasColumn('comvis_orcamento_itens', $coluna)) {
                Schema::table('comvis_orcamento_itens', fn (Blueprint $t) => $t->dropColumn($coluna));
            }
        }
        if (Schema::hasTable('comvis_materiais') && Schema::hasColumn('comvis_materiais', 'minimo_m2')) {
            Schema::table('comvis_materiais', fn (Blueprint $t) => $t->dropColumn('minimo_m2'));
        }
    }
};
