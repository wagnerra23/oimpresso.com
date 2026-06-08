<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-04 — Conta bancária PREVISTA no título (editável).
 *
 * Pedido Wagner: poder trocar a conta no Editar do lançamento. A conta REALIZADA
 * vive na baixa (fin_titulo_baixas.conta_bancaria_id), mas só nasce ao quitar.
 * Esta coluna guarda a conta PREVISTA/escolhida — editável enquanto em aberto.
 *
 * Exibição (UnificadoController::shapeTitulo / coluna "Conta"):
 *   conta exibida = última baixa.contaBancaria (realizada) ?? titulo.conta_bancaria (prevista)
 *
 * Espelha o pattern de forma_pagamento. Aditivo e seguro: nullable, sem default,
 * sem backfill, FK soft (sem constraint — ContaBancaria pode ser de qualquer scope
 * do business; validação no Request/Controller).
 */
class AddContaBancariaIdToFinTitulos extends Migration
{
    public function up(): void
    {
        Schema::table('fin_titulos', function (Blueprint $table) {
            $table->integer('conta_bancaria_id')->unsigned()->nullable()->after('forma_pagamento');
            $table->index(['business_id', 'conta_bancaria_id'], 'idx_fin_titulos_conta');
        });
    }

    public function down(): void
    {
        Schema::table('fin_titulos', function (Blueprint $table) {
            $table->dropIndex('idx_fin_titulos_conta');
            $table->dropColumn('conta_bancaria_id');
        });
    }
}
