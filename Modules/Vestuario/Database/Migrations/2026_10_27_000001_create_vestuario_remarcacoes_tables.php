<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-VEST-032 — remarcação por coleção. Cada remarcação é um lote; cada variação remarcada guarda
 * o preço de antes e o de depois, para a remarcação poder ser revertida (regra mestre de valor).
 * Tier 0: business_id em todas as linhas (ADR 0093).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('vestuario_remarcacoes')) {
            Schema::create('vestuario_remarcacoes', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('business_id');
                $t->string('nome', 80);
                $t->json('filtro');
                $t->string('modo', 12); // percentual | valor
                $t->decimal('valor', 12, 4);
                $t->unsignedInteger('qtd_itens');
                $t->string('status', 12)->default('aplicada'); // aplicada | revertida
                $t->unsignedInteger('aplicada_por');
                $t->unsignedInteger('revertida_por')->nullable();
                $t->timestamp('revertida_em')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'created_at'], 'idx_vest_remarc_biz_data');
            });
        }
        if (! Schema::hasTable('vestuario_remarcacao_itens')) {
            Schema::create('vestuario_remarcacao_itens', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('business_id');
                $t->unsignedBigInteger('remarcacao_id');
                $t->unsignedInteger('product_id');
                $t->unsignedInteger('variation_id');
                $t->decimal('preco_antes', 22, 4);   // sell_price_inc_tax
                $t->decimal('preco_depois', 22, 4);
                $t->decimal('venda_antes', 22, 4);   // default_sell_price
                $t->decimal('venda_depois', 22, 4);
                $t->decimal('lucro_antes', 22, 4);   // profit_percent
                $t->decimal('lucro_depois', 22, 4);
                $t->string('reversao', 12)->nullable(); // revertido | conflito
                $t->timestamps();
                $t->index(['business_id', 'remarcacao_id'], 'idx_vest_remarc_it_lote');
                $t->index(['business_id', 'variation_id'], 'idx_vest_remarc_it_var');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vestuario_remarcacao_itens');
        Schema::dropIfExists('vestuario_remarcacoes');
    }
};
