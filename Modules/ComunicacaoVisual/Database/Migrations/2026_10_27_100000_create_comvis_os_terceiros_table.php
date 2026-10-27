<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-COMVIS-005 — custo de terceiros da OS da gráfica (serviço comprado fora: corte CNC, instalação
 * terceirizada, frete). Entra no custo real do pós-cálculo; não mexe em preço nem em estoque.
 *
 * Tier 0 (ADR 0093): `business_id` com índice e FK; toda leitura filtra por ele.
 * Idempotente: só cria se a tabela não existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comvis_os_terceiros')) {
            return;
        }

        Schema::create('comvis_os_terceiros', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index('comvis_os_terc_biz_idx');
            $t->unsignedBigInteger('os_id');
            $t->string('descricao', 150);
            $t->unsignedInteger('fornecedor_id')->nullable()->comment('contacts.id do fornecedor, opcional');
            $t->decimal('valor', 15, 2);
            $t->unsignedInteger('created_by')->nullable();
            $t->timestamps();

            $t->index(['business_id', 'os_id'], 'comvis_os_terc_biz_os_idx');
            $t->foreign('business_id', 'comvis_os_terc_biz_fk')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('os_id', 'comvis_os_terc_os_fk')->references('id')->on('comvis_os')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comvis_os_terceiros');
    }
};
