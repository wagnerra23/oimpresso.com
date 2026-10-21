<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Romaneio de impressão peça a peça (UC-FLX-12 · US-PCP-023): a lista do que a Pré-impressão mandou
 * para o setor de impressão e corte de uma OS de comunicação visual.
 *
 * As linhas são CÓPIA dos itens do orçamento da OS no momento em que o romaneio foi gerado
 * (descrição, mídia, largura × altura, quantidade): orçamento mudado depois não reescreve o que já
 * foi para a máquina. Romaneio gravado não muda; gerar de novo cria a versão seguinte.
 * Multi-tenant Tier 0 (ADR 0093): `business_id` nas duas tabelas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pcp_romaneios')) {
            Schema::create('pcp_romaneios', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->string('os_tipo', 30);
                $t->unsignedBigInteger('os_id');
                $t->unsignedInteger('versao');
                $t->string('numero', 40);
                $t->foreignId('programacao_id')->constrained('pcp_programacoes');
                $t->unsignedBigInteger('posto_id')->nullable();
                $t->string('posto_nome', 120)->nullable();
                $t->unsignedInteger('gerado_por');
                $t->dateTime('gerado_em');
                $t->timestamps();
                $t->unique(['business_id', 'os_tipo', 'os_id', 'versao'], 'pcp_romaneios_biz_os_versao_uq');
                $t->index(['business_id', 'os_tipo', 'os_id'], 'pcp_romaneios_biz_os_idx');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('pcp_romaneio_linhas')) {
            Schema::create('pcp_romaneio_linhas', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->foreignId('romaneio_id')->constrained('pcp_romaneios')->cascadeOnDelete();
                $t->unsignedBigInteger('orcamento_item_id')->nullable();
                $t->unsignedInteger('ordem');
                $t->string('descricao', 255);
                $t->unsignedBigInteger('material_id')->nullable();
                $t->string('material_nome', 150)->nullable();
                $t->decimal('largura_m', 8, 3)->nullable();
                $t->decimal('altura_m', 8, 3)->nullable();
                $t->integer('quantidade');
                $t->decimal('area_m2', 10, 3)->nullable();
                $t->timestamps();
                $t->index(['business_id', 'orcamento_item_id'], 'pcp_romaneio_linhas_biz_item_idx');
                $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pcp_romaneio_linhas');
        Schema::dropIfExists('pcp_romaneios');
    }
};
