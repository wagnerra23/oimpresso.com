<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration — `service_orders.transaction_sell_line_id` (granularidade OS↔Linha).
 *
 * Estende a coluna `transaction_id` (1 OS ↔ 1 Venda inteira) com granularidade
 * por linha de produto. Suporta os 2 modos canônicos de operação:
 *
 *   1. **single (1 OS pra venda toda)** — caso Martinho (caçambas):
 *      transaction_id=X, transaction_sell_line_id=NULL
 *      "1 venda, 1 OS cobrindo todos itens"
 *
 *   2. **per_line (1 OS por linha de produto)** — caso ComunicacaoVisual (gráfica):
 *      transaction_id=X, transaction_sell_line_id=Y
 *      "1 venda com N produtos = N OS, cada uma com sua linha"
 *
 * Sem FK em `transaction_sell_line_id` (pattern Wave 5-A `current_rental_id`
 * em vehicles) — evita cascade nightmare se sellLine for soft-deleted/restored.
 * Cleanup orquestrado via Service / Job, não via FK CASCADE.
 *
 * INDEX composto `(business_id, transaction_id, transaction_sell_line_id)` cobre
 * a query mais frequente: "todas OS desta venda" (drawer SaleSheet).
 *
 * Multi-tenant Tier 0 ([ADR 0093]): business_id JÁ indexado pela migration anterior.
 *
 * Idempotente (Schema::hasColumn guard) — reversível.
 *
 * @see Modules/OficinaAuto/Database/Migrations/2026_05_11_000020_create_service_orders_table.php
 * @see app/Services/CriarOsPorVendaService.php
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('service_orders', 'transaction_sell_line_id')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->unsignedBigInteger('transaction_sell_line_id')
                    ->nullable()
                    ->after('transaction_id')
                    ->comment('Linha de produto (transaction_sell_lines.id) que originou a OS. NULL = OS cobre venda toda (modo single, caso Martinho). Sem FK pra evitar cascade soft-delete.');

                // INDEX composto: query "todas OS desta venda+linha" (drawer SaleSheet, audit).
                $table->index(
                    ['business_id', 'transaction_id', 'transaction_sell_line_id'],
                    'idx_so_biz_tx_sell_line'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('service_orders', 'transaction_sell_line_id')) {
            Schema::table('service_orders', function (Blueprint $table) {
                $table->dropIndex('idx_so_biz_tx_sell_line');
                $table->dropColumn('transaction_sell_line_id');
            });
        }
    }
};
