<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cartão da maquininha (US-FIN-081 · UC-FCC-23..26).
 *
 * `fin_cartao_adquirentes`: a maquininha da empresa (Stone, Cielo, Rede…), com a taxa e o prazo
 * de repasse do débito e do crédito, e o trecho que identifica o repasse dela na descrição do
 * extrato. Uma por business é a padrão — é a que recebe as vendas no cartão.
 *
 * `fin_cartao_recebiveis`: o que a adquirente deve pagar por cada pagamento de venda no cartão —
 * bruto, taxa, líquido e a data prevista. Nasce `previsto`; o repasse no extrato o deixa
 * `recebido` (com a linha do extrato que pagou); apagar o pagamento o deixa `cancelado`.
 * O UNIQUE (business, pagamento) é a idempotência: o observer pode rodar de novo sem duplicar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_cartao_adquirentes')) {
            Schema::create('fin_cartao_adquirentes', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('business_id');
                $t->string('nome', 80);
                $t->string('descricao_extrato', 80)->comment('trecho que identifica o repasse no extrato, ex.: STONE');
                $t->unsignedInteger('conta_bancaria_id')->nullable()->comment('conta onde cai o repasse');
                $t->decimal('taxa_debito', 6, 3)->default(0)->comment('% sobre o bruto');
                $t->unsignedSmallInteger('prazo_debito_dias')->default(1);
                $t->decimal('taxa_credito', 6, 3)->default(0)->comment('% sobre o bruto');
                $t->unsignedSmallInteger('prazo_credito_dias')->default(30);
                $t->boolean('padrao')->default(false);
                $t->boolean('ativo')->default(true);
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['business_id', 'ativo'], 'fin_cartao_adq_biz_ativo_idx');
                $t->foreign('business_id', 'fin_cartao_adq_biz_fk')
                    ->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('fin_cartao_recebiveis')) {
            Schema::create('fin_cartao_recebiveis', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('business_id');
                $t->unsignedInteger('adquirente_id');
                $t->unsignedInteger('transaction_id')->nullable();
                $t->unsignedInteger('transaction_payment_id');
                $t->string('modalidade', 10)->comment('debito | credito');
                $t->date('data_venda');
                $t->date('data_prevista');
                $t->decimal('valor_bruto', 15, 2);
                $t->decimal('taxa_percentual', 6, 3);
                $t->decimal('valor_taxa', 15, 2);
                $t->decimal('valor_liquido', 15, 2);
                $t->string('status', 12)->default('previsto')->comment('previsto | recebido | cancelado');
                $t->string('extrato_origem', 3)->nullable()->comment('ofx | api — linha que pagou');
                $t->unsignedBigInteger('extrato_linha_id')->nullable();
                $t->date('recebido_em')->nullable();
                $t->timestamps();

                $t->unique(['business_id', 'transaction_payment_id'], 'fin_cartao_rec_biz_tp_uq');
                $t->index(['business_id', 'status', 'data_prevista'], 'fin_cartao_rec_biz_status_idx');
                $t->index(['business_id', 'extrato_origem', 'extrato_linha_id'], 'fin_cartao_rec_biz_linha_idx');
                $t->foreign('business_id', 'fin_cartao_rec_biz_fk')
                    ->references('id')->on('business')->cascadeOnDelete();
                $t->foreign('adquirente_id', 'fin_cartao_rec_adq_fk')
                    ->references('id')->on('fin_cartao_adquirentes');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_cartao_recebiveis');
        Schema::dropIfExists('fin_cartao_adquirentes');
    }
};
