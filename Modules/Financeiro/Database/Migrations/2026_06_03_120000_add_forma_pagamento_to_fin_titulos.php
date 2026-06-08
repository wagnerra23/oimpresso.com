<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026-06-03 — Forma de pagamento PREVISTA no título.
 *
 * Pedido Wagner: a tela /financeiro/unificado não mostrava como o lançamento
 * é/será pago (boleto, dinheiro, cartão…). A forma REALIZADA já vive na baixa
 * (fin_titulo_baixas.meio_pagamento), mas só nasce ao quitar. Esta coluna guarda
 * a forma PREVISTA/escolhida — editável enquanto o título está em aberto.
 *
 * Regra de exibição na UI (UnificadoController::shapeTitulo):
 *   forma exibida = última baixa.meio_pagamento (realizada) ?? titulo.forma_pagamento (prevista) ?? null
 *
 * Enum espelha fin_titulo_baixas.meio_pagamento (mesma lista canônica).
 * Backward compat: títulos antigos têm forma_pagamento = NULL (mostra "—").
 * Aditivo e seguro: nullable, sem default obrigatório, sem backfill.
 */
class AddFormaPagamentoToFinTitulos extends Migration
{
    public function up(): void
    {
        Schema::table('fin_titulos', function (Blueprint $table) {
            $table->enum('forma_pagamento', [
                'dinheiro',
                'pix',
                'boleto',
                'cartao_credito',
                'cartao_debito',
                'transferencia',
                'cheque',
                'compensacao',
                'outro',
            ])->nullable()->after('vencimento');
        });
    }

    public function down(): void
    {
        Schema::table('fin_titulos', function (Blueprint $table) {
            $table->dropColumn('forma_pagamento');
        });
    }
}
