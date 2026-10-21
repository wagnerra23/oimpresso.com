<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UC-FLX-01 · US-COMVIS-020 — itens da OS de comunicação visual, copiados do orçamento aprovado.
 *
 * Guarda as medidas que a produção usa (descrição, material, largura, altura, quantidade, área)
 * e de qual item do orçamento cada linha veio. NÃO guarda preço: o valor da OS é o total do
 * orçamento (`comvis_os.valor_total`), e nada aqui recalcula preço.
 *
 * `uq_comvis_os_itens_orc_item` garante uma linha por item do orçamento por empresa: aprovar de
 * novo não duplica. Idempotente: só cria a tabela se faltar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comvis_os_itens')) {
            return;
        }

        Schema::create('comvis_os_itens', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('os_id');
            $t->unsignedBigInteger('orcamento_item_id')->nullable();
            $t->unsignedBigInteger('material_id')->nullable();
            $t->string('descricao', 255);
            $t->decimal('largura_m', 8, 3)->nullable();
            $t->decimal('altura_m', 8, 3)->nullable();
            $t->integer('quantidade')->default(1);
            $t->decimal('area_m2', 10, 3)->nullable();
            $t->text('observacoes')->nullable();
            $t->integer('ordem')->default(0);
            $t->timestamps();

            $t->index(['business_id', 'os_id'], 'idx_comvis_os_itens_biz_os');
            $t->unique(['business_id', 'orcamento_item_id'], 'uq_comvis_os_itens_orc_item');

            $t->foreign('business_id', 'fk_comvis_os_itens_business')
                ->references('id')->on('business')->onDelete('cascade');
            $t->foreign('os_id', 'fk_comvis_os_itens_os')
                ->references('id')->on('comvis_os')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comvis_os_itens');
    }
};
