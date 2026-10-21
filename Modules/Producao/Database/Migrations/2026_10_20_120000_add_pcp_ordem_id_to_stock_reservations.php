<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-PCP-031 · UC-FLX-14 — a reserva do insumo de um item sob encomenda pertence a uma OS por item.
 *
 * `stock_reservations` (US-SELL-013) só conhecia a venda. A venda tem uma OS por item, e o insumo
 * reservado por uma OS é abatido pelo apontamento DELA e liberado quando ELA conclui ou é cancelada.
 * A coluna é nula para toda reserva que não nasce de uma OS (as do motor FSM seguem como estão).
 * Sem FK: a reserva é do núcleo e a OS é do módulo Producao (o núcleo não depende de módulo).
 * Multi-tenant: o índice começa por `business_id` (ADR 0093). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_reservations') || Schema::hasColumn('stock_reservations', 'pcp_ordem_id')) {
            return;
        }
        Schema::table('stock_reservations', function (Blueprint $t) {
            $t->unsignedBigInteger('pcp_ordem_id')->nullable()->after('transaction_id');
            $t->index(['business_id', 'pcp_ordem_id', 'variation_id'], 'stock_res_biz_ordem_var_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_reservations') || ! Schema::hasColumn('stock_reservations', 'pcp_ordem_id')) {
            return;
        }
        Schema::table('stock_reservations', function (Blueprint $t) {
            $t->dropIndex('stock_res_biz_ordem_var_idx');
            $t->dropColumn('pcp_ordem_id');
        });
    }
};
