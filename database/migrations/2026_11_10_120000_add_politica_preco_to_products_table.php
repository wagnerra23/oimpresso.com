<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Política de preço do produto (CU-PROD-21): o que acontece com o preço de venda quando o custo dos
 * insumos muda na compra. `fixo` (só a margem muda) · `acompanha` (mantém o markup) · `margem`
 * (recalcula pela margem alvo, que é sobre o preço de venda).
 *
 * Esta migração só grava a escolha. Quem vai ler é o recebimento da compra, quando a regra da
 * compra em Fluxos estiver em "Política de preço do produto" (`RegrasCompra`, parâmetro `regraEm`);
 * até lá nenhum preço muda por causa destas colunas. Produto que já existe fica `fixo`, o padrão do
 * protótipo e a resposta do [W] na US-PROD-030; a migração do legado (`TEM_MARGEM_FIXA_CONTIBUICAO`)
 * é outro passo.
 *
 * Uma escolha por produto vale para todas as variações, como o método de custo. `products` já
 * carrega `business_id` (Tier 0, ADR 0093).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'politica_preco')) {
                $table->string('politica_preco', 10)->default('fixo')
                    ->comment('fixo | acompanha | margem — o que o preço faz quando o custo muda (CU-PROD-21)');
            }
            if (! Schema::hasColumn('products', 'margem_alvo_pct')) {
                $table->decimal('margem_alvo_pct', 5, 2)->nullable()
                    ->comment('Margem alvo sobre o preço de venda, só com politica_preco = margem (CU-PROD-21)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['margem_alvo_pct', 'politica_preco'] as $coluna) {
                if (Schema::hasColumn('products', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
