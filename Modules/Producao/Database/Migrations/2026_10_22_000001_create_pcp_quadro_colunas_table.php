<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna manual da OS no quadro (thread 06 · UC-PCV-07). O PCP arrasta o cartão (ou usa "Mover para")
 * e a OS passa a aparecer no setor escolhido; nenhuma etapa, apontamento ou situação muda (o protótipo
 * só troca a coluna). Uma linha por OS: mover de novo regrava.
 *
 * `ate_evento_id` = o último evento de etapa iniciada/concluída da OS no momento do movimento. Quando
 * o operador anda (evento mais novo que esse), a coluna manual deixa de valer e o quadro volta à derivada.
 *
 * Tabela nova porque a OS é polimórfica (`os_tipo` + `os_id`: comvis_os ou pcp_ordem) e não há uma
 * tabela única onde pôr a coluna. Tier 0 (ADR 0093): `business_id` indexado + FK, chave única com a empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pcp_quadro_colunas')) {
            return;
        }

        Schema::create('pcp_quadro_colunas', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->index();
            $t->string('os_tipo', 40);
            $t->unsignedBigInteger('os_id');
            $t->string('setor', 60);
            $t->unsignedBigInteger('ate_evento_id')->nullable()->comment('último evento de etapa da OS quando foi movida');
            $t->unsignedInteger('movido_por');
            $t->timestamps();
            $t->unique(['business_id', 'os_tipo', 'os_id'], 'pcp_quadro_colunas_biz_os_uq');
            $t->foreign('business_id')->references('id')->on('business')->cascadeOnDelete();
            $t->foreign('movido_por')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pcp_quadro_colunas');
    }
};
