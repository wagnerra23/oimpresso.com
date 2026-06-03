<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-CRM-081 -- FK de rastreio `shipping_address_id` em `transactions`.
 *
 * RASTREIO apenas: aponta pro contact_addresses escolhido na venda. NAO
 * substitui o snapshot `transactions.shipping_address` (text), que permanece
 * a string CONGELADA no fechamento (imutavel -- editar/deletar o endereco do
 * contato depois NAO altera vendas passadas). Pegadinha 3 do dossier.
 *
 * nullable (vendas legado + walk-in sem endereco). onDelete('set null') --
 * deletar o endereco do contato preserva a venda (snapshot string sobrevive,
 * so perde o ponteiro de rastreio).
 *
 * IDEMPOTENTE (Schema::hasColumn). down() dropa FK + coluna.
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/sessions/2026-06-02-coord-multiplos-enderecos-entrega.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'shipping_address_id')) {
                $table->unsignedBigInteger('shipping_address_id')->nullable()->after('shipping_address');
                $table->foreign('shipping_address_id')
                    ->references('id')->on('contact_addresses')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'shipping_address_id')) {
                $table->dropForeign(['shipping_address_id']);
                $table->dropColumn('shipping_address_id');
            }
        });
    }
};
