<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-SELL-054 — venda feita sem internet no caixa e reenviada ao voltar a conexão.
 *
 * Uma linha por venda offline recebida: a chave que o navegador gerou na hora da
 * venda (`offline_uuid`) aponta para a venda gravada. O UNIQUE por empresa é a
 * trava: o mesmo reenvio, mesmo concorrente, não grava duas vendas nem baixa o
 * estoque duas vezes. Tabela-ponte para não alterar `transactions`.
 *
 * Multi-tenant Tier 0 (ADR 0093): business_id NOT NULL + índice.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendas_offline_recebidas')) {
            return;
        }

        Schema::create('vendas_offline_recebidas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id')->index();
            $table->uuid('offline_uuid');
            $table->unsignedInteger('transaction_id')->index();
            $table->timestamp('vendida_em')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'offline_uuid'], 'vendas_offline_biz_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendas_offline_recebidas');
    }
};
