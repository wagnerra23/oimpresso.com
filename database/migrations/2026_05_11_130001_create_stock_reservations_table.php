<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-013 — Reservas de estoque (ADR 0129 + caso prático Comunicação Visual).
 *
 * Reserva impede que o mesmo metro de lona seja vendido em 2 OS simultâneas
 * mas mantém estoque disponível enquanto OS pode ser cancelada (não baixa
 * `variation_location_details.qty_available` até `consumed`).
 *
 * Multi-tenant Tier 0 (ADR 0093) — business_id obrigatório + global scope.
 *
 * Status enum:
 *  - active   — reserva viva, conta no cálculo de "disponível pra venda"
 *  - consumed — produção concluída, qty_available já decrementado
 *  - released — OS cancelada antes da produção
 *  - expired  — TTL vencido (Job daily ExpireStaleReservationsJob)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('transaction_id');
            $t->unsignedInteger('product_id');
            $t->unsignedInteger('variation_id');
            $t->unsignedInteger('location_id');
            $t->decimal('qty_reserved', 22, 4)->default(0);
            $t->enum('status', ['active', 'consumed', 'released', 'expired'])->default('active');
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();

            $t->index(['business_id', 'transaction_id'], 'stock_res_biz_tx_idx');
            $t->index(['business_id', 'product_id', 'variation_id', 'status'], 'stock_res_avail_idx');
            $t->index(['status', 'expires_at'], 'stock_res_expire_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
