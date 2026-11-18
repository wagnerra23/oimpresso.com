<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0448, etapa 1 (núcleo): as colunas que a composição única vai usar. Ninguém lê ainda.
 *
 * - `units.tipo_medida` (`area`, `comprimento`, `contagem`, `folha`; nulo = não informado): de onde
 *   vem a regra de consumo padrão do insumo. Coluna explícita porque o nome da unidade não serve
 *   (no legado "ml" e "ML" só diferem por maiúscula).
 * - `product_bom.regra_consumo` (`area`, `perimetro`, `unidade`, `aproveitamento`, `area_taxa`,
 *   `perimetro_passo`; nulo = segue a unidade do insumo, senão `unidade`) e `passo_cm` (o "um a
 *   cada N cm" do `perimetro_passo`). D6.
 * - `product_bom.por_quantidade` (padrão 1): `qty_required` vale para essa quantidade do pai, para
 *   o rendimento da receita não virar divisão gravada.
 * - `product_bom.sub_unit_id` e `ingredient_group_id`: o que as linhas de `mfg_recipe_ingredients`
 *   têm e a cópia da etapa 2 precisa levar. Sem chave estrangeira para o Manufacturing: o núcleo
 *   não depende do módulo (DependencyDirectionTest).
 * - `product_bom.opcional_por`: a mesma chave de `pcp_roteiro_etapas.opcional_por` (ADR 0448 §4).
 *
 * Texto curto em vez de enum: a lista de valores vive no código que for ler (etapas 3 a 5).
 * `product_bom` já tem `business_id` e escopo por empresa; as colunas novas herdam (ADR 0093).
 * Idempotente: cada coluna só entra se ainda não existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('units') && ! Schema::hasColumn('units', 'tipo_medida')) {
            Schema::table('units', function (Blueprint $t) {
                $t->string('tipo_medida', 20)->nullable();
            });
        }

        if (! Schema::hasTable('product_bom')) {
            return;
        }

        $colunas = [
            'regra_consumo' => fn (Blueprint $t) => $t->string('regra_consumo', 20)->nullable(),
            'passo_cm' => fn (Blueprint $t) => $t->decimal('passo_cm', 10, 2)->nullable(),
            'por_quantidade' => fn (Blueprint $t) => $t->decimal('por_quantidade', 22, 4)->default(1),
            'sub_unit_id' => fn (Blueprint $t) => $t->unsignedInteger('sub_unit_id')->nullable(),
            'ingredient_group_id' => fn (Blueprint $t) => $t->unsignedInteger('ingredient_group_id')->nullable(),
            'opcional_por' => fn (Blueprint $t) => $t->string('opcional_por', 80)->nullable(),
        ];

        foreach ($colunas as $nome => $definir) {
            if (! Schema::hasColumn('product_bom', $nome)) {
                Schema::table('product_bom', $definir);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_bom')) {
            foreach (['opcional_por', 'ingredient_group_id', 'sub_unit_id', 'por_quantidade', 'passo_cm', 'regra_consumo'] as $nome) {
                if (Schema::hasColumn('product_bom', $nome)) {
                    Schema::table('product_bom', fn (Blueprint $t) => $t->dropColumn($nome));
                }
            }
        }

        if (Schema::hasTable('units') && Schema::hasColumn('units', 'tipo_medida')) {
            Schema::table('units', fn (Blueprint $t) => $t->dropColumn('tipo_medida'));
        }
    }
};
