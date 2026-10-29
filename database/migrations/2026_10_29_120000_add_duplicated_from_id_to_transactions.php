<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-031 / P-035 — a venda duplicada guarda de qual venda veio.
 *
 * `SellController::duplicateSell` copia a venda para um rascunho; esta coluna guarda o id da
 * venda de origem, para mostrar "Duplicada de V-x" na cópia e "Duplicada em V-y" na origem.
 * NULL = venda que não nasceu de duplicação (toda venda de antes desta migration).
 *
 * Sem FK física, como as outras colunas de vínculo de `transactions` (compat UltimatePOS).
 * O índice começa por `business_id` porque toda leitura do vínculo é escopada pela empresa
 * (ADR 0093): "quais vendas desta empresa foram duplicadas da V-x?".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'duplicated_from_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedInteger('duplicated_from_id')->nullable();
            $table->index(['business_id', 'duplicated_from_id'], 'transactions_biz_duplicated_from_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('transactions', 'duplicated_from_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_biz_duplicated_from_idx');
            $table->dropColumn('duplicated_from_id');
        });
    }
};
