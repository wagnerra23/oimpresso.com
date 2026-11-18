<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0448, etapa 1 (Produção): as colunas que a composição única e a ordem para estoque vão usar.
 * Ninguém lê nem grava ainda.
 *
 * - `pcp_bobinas.comprimento_m` (nulo = bobina contínua; preenchido = chapa ou folha) e
 *   `variation_id`: o tamanho físico da mídia passa a apontar também para o produto, que é o que o
 *   `product_bom` usa. Sem isso a regra `aproveitamento` não acha a mídia (ADR 0448 §3).
 * - `pcp_ordens.transaction_id` e `transaction_sell_line_id` passam a aceitar nulo, e entra
 *   `origem` (`venda`, `estoque`; padrão `venda`, que é o que toda ordem existente é). A ordem
 *   para estoque não tem venda (ADR 0448 §5). A unicidade por linha da venda continua valendo:
 *   no MySQL o índice único não compara nulos, então só as ordens com venda são únicas por linha.
 *
 * Multi-tenant Tier 0 (ADR 0093): as duas tabelas já têm `business_id` com chave estrangeira.
 * Idempotente: cada mudança só roda se ainda não foi feita.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcp_bobinas')) {
            if (! Schema::hasColumn('pcp_bobinas', 'comprimento_m')) {
                Schema::table('pcp_bobinas', fn (Blueprint $t) => $t->decimal('comprimento_m', 8, 3)->nullable());
            }
            if (! Schema::hasColumn('pcp_bobinas', 'variation_id')) {
                Schema::table('pcp_bobinas', function (Blueprint $t) {
                    $t->unsignedInteger('variation_id')->nullable();
                    $t->index(['business_id', 'variation_id'], 'pcp_bobinas_biz_variation_idx');
                });
            }
        }

        if (Schema::hasTable('pcp_ordens') && ! Schema::hasColumn('pcp_ordens', 'origem')) {
            Schema::table('pcp_ordens', function (Blueprint $t) {
                $t->unsignedInteger('transaction_id')->nullable()->change();
                $t->unsignedInteger('transaction_sell_line_id')->nullable()->change();
                $t->string('origem', 10)->default('venda');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pcp_ordens') && Schema::hasColumn('pcp_ordens', 'origem')) {
            // Volta a exigir venda: só é seguro enquanto nenhuma ordem para estoque existir.
            Schema::table('pcp_ordens', function (Blueprint $t) {
                $t->dropColumn('origem');
                $t->unsignedInteger('transaction_id')->nullable(false)->change();
                $t->unsignedInteger('transaction_sell_line_id')->nullable(false)->change();
            });
        }

        if (Schema::hasTable('pcp_bobinas')) {
            if (Schema::hasColumn('pcp_bobinas', 'variation_id')) {
                Schema::table('pcp_bobinas', function (Blueprint $t) {
                    $t->dropIndex('pcp_bobinas_biz_variation_idx');
                    $t->dropColumn('variation_id');
                });
            }
            if (Schema::hasColumn('pcp_bobinas', 'comprimento_m')) {
                Schema::table('pcp_bobinas', fn (Blueprint $t) => $t->dropColumn('comprimento_m'));
            }
        }
    }
};
