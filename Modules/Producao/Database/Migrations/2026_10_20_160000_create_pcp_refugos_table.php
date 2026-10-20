<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Refugo com motivo na etapa da OS por item (UC-FLX-16 · US-PCP-026). Uma linha por apontamento de
 * refugo: quantas peças, o motivo do cadastro do posto (`pcp_motivos`, tipo `refugo`) e quem/quando.
 * O material reposto para as peças refugadas é baixado pelo ConsumoMaterialService (`pcp_consumos`,
 * chave derivada da chave do refugo); por isso o custo do refugo já entra no custo real da OS.
 *
 * Idempotente pela `chave` (fila offline do celular), como o material e o apontamento de horas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcp_refugos')) {
            return;
        }

        Schema::create('pcp_refugos', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('chave', 64);
            $t->foreignId('ordem_id')->constrained('pcp_ordens')->cascadeOnDelete();
            $t->foreignId('programacao_id')->constrained('pcp_programacoes');
            $t->unsignedBigInteger('motivo_id');
            // Nome do motivo copiado: renomear o cadastro depois não reescreve o histórico.
            $t->string('motivo', 120);
            $t->decimal('quantidade', 22, 4);
            // Etapa nova aberta pelo "Reprovar no controle", quando o refugo mandou a OS para retrabalho.
            $t->unsignedBigInteger('retrabalho_programacao_id')->nullable();
            $t->unsignedInteger('user_id');
            $t->dateTime('momento');
            $t->timestamps();
            $t->unique(['business_id', 'chave'], 'pcp_refugos_biz_chave_uq');
            $t->index(['business_id', 'ordem_id'], 'pcp_refugos_biz_ordem_idx');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcp_refugos');
    }
};
