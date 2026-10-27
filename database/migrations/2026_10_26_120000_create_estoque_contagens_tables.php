<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-ESTOQUE-002 — contagem de inventário.
 *
 * `estoque_contagens`: uma contagem por local, aberta → fechada → ajustada (ou cancelada).
 * `estoque_contagem_itens`: o que foi contado de cada variação, com o saldo do sistema no
 * instante da PRIMEIRA contagem do item (`saldo_no_momento`). A divergência é
 * `qtd_contada − saldo_no_momento`; o ajuste aplica essa diferença sobre o saldo de quando for
 * aprovado, então venda feita depois de contar a prateleira não vira falta falsa.
 *
 * Tier 0 (ADR 0093): `business_id` indexado nas duas tabelas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estoque_contagens')) {
            Schema::create('estoque_contagens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->unsignedInteger('location_id');
                $table->string('descricao', 191)->nullable();
                $table->enum('status', ['aberta', 'fechada', 'ajustada', 'cancelada'])->default('aberta');
                $table->unsignedInteger('aberta_por');
                $table->timestamp('aberta_em')->nullable();
                $table->unsignedInteger('fechada_por')->nullable();
                $table->timestamp('fechada_em')->nullable();
                $table->unsignedInteger('ajustada_por')->nullable();
                $table->timestamp('ajustada_em')->nullable();
                $table->timestamps();

                $table->index(['business_id', 'status'], 'est_cont_biz_status_idx');
                $table->index(['business_id', 'location_id'], 'est_cont_biz_loc_idx');
                $table->foreign('business_id', 'est_cont_biz_fk')->references('id')->on('business')->onDelete('cascade');
            });
        }

        if (! Schema::hasTable('estoque_contagem_itens')) {
            Schema::create('estoque_contagem_itens', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id');
                $table->unsignedBigInteger('contagem_id');
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('variation_id');
                $table->decimal('qtd_contada', 22, 4)->default(0);
                $table->decimal('saldo_no_momento', 22, 4)->default(0);
                $table->unsignedInteger('contado_por');
                $table->timestamp('contado_em')->nullable();
                $table->timestamps();

                $table->unique(['contagem_id', 'variation_id'], 'est_cont_item_var_uq');
                $table->index('business_id', 'est_cont_item_biz_idx');
                $table->foreign('contagem_id', 'est_cont_item_cont_fk')->references('id')->on('estoque_contagens')->onDelete('cascade');
                $table->foreign('business_id', 'est_cont_item_biz_fk')->references('id')->on('business')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estoque_contagem_itens');
        Schema::dropIfExists('estoque_contagens');
    }
};
