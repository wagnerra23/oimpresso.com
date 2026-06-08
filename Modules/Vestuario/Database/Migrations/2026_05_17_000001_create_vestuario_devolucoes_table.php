<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration vestuario_devolucoes — Wave 28 G2 W22 (CAPTERRA Vestuario).
 *
 * Devolução/troca CDC Art. 26 (30 dias produtos não-duráveis = vestuário)
 * com 4 tipos canônicos: troca_mesmo_produto, troca_outro_produto,
 * credito_ficha, estorno_dinheiro.
 *
 * Append-only audit (Tier 0 IRREVOGÁVEL — analógico a ponto_marcacoes
 * Portaria 671/2021): UPDATE/DELETE direto proibido em produção.
 * Correção via nova linha tipo `estorno_dinheiro` referenciando registro
 * original. softDeletes apenas pra GDPR/LGPD (purge legal).
 *
 * Taxa devolução vestuário BR/EUA: 30-40% online, 15-25% loja física
 * (NRF + Fecomercio fontes 2026). Linx "Troca Fácil" + Bling/Tiny
 * "Vale-Trocas" são padrão setor.
 *
 * Multi-tenant Tier 0 ([ADR 0093]):
 * - business_id NOT NULL indexed
 * - FK opcional (descomentar pós validação schema business UltimatePOS)
 *
 * @see Modules/Vestuario/Services/DevolucaoService.php
 * @see memory/requisitos/Vestuario/CAPTERRA-FICHA.md (W22 G2)
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vestuario_devolucoes')) {
            return;
        }

        Schema::create('vestuario_devolucoes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('transaction_id'); // venda original
            $table->unsignedBigInteger('transaction_sell_line_id'); // item específico
            $table->unsignedSmallInteger('quantidade_devolvida');
            $table->decimal('valor_devolvido', 10, 2);
            $table->enum('tipo', [
                'troca_mesmo_produto',
                'troca_outro_produto',
                'credito_ficha',
                'estorno_dinheiro',
            ]);
            $table->text('motivo'); // CDC Art. 26 — operador registra justificativa
            $table->unsignedBigInteger('processed_by_user_id');
            $table->timestamp('processed_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index('business_id', 'idx_vest_devol_business');
            $table->index(['business_id', 'transaction_id'], 'idx_vest_devol_biz_tx');
            $table->index(['business_id', 'tipo'], 'idx_vest_devol_biz_tipo');
            $table->index('processed_at', 'idx_vest_devol_processed_at');

            // FK descomentar após validar schema business UltimatePOS em homolog
            // $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            // $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('restrict');
            // $table->foreign('processed_by_user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vestuario_devolucoes');
    }
};
