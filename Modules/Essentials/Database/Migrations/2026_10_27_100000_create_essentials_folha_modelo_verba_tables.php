<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelo de verba da folha CLT — passo 3 do §9 da ADR-mãe da folha
 * (memory/decisions/proposals/2026-09-05-folha-com-encargos-modelo-e-fronteira.md, §2.7).
 *
 * Quatro tabelas, só o contrato: nenhuma calcula valor e nenhuma é lida pela folha gerencial de hoje
 * (`transactions.essentials_allowances`). Motor, tabela legal e tela vêm nos passos seguintes, atrás
 * da flag `essentials.folha_clt.enabled` (desligada).
 *
 *   rubricas            catálogo por business (código, natureza, sentido)
 *   rubrica_vigencias   as três incidências (INSS, IRRF, FGTS) por vigência AAAA-MM — independentes,
 *                       porque "aviso prévio indenizado" é ❌ ❌ ✅ e nenhum enum ordenado o expressa (§2.2)
 *   competencias        o fechamento AAAA-MM por business
 *   itens               a verba lançada, com DUAS competências (pagamento × apuração, §2.5) e proveniência
 *
 * Competência é `char(7)` AAAA-MM: comparar como texto dá a ordem cronológica.
 * Tier 0 (ADR 0093): `business_id` indexado com FK em todas.
 */
class CreateEssentialsFolhaModeloVerbaTables extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('essentials_folha_rubricas')) {
            Schema::create('essentials_folha_rubricas', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('business_id')->index();
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->string('codigo', 30);
                $table->string('descricao', 100);
                $table->string('natureza', 4)->comment('natRubr da Tabela 3 do eSocial, só como classificação');
                $table->enum('sentido', ['provento', 'desconto', 'base', 'informativa']);
                $table->timestamps();
                $table->unique(['business_id', 'codigo'], 'ess_folha_rubricas_biz_codigo_uq');
            });
        }

        if (! Schema::hasTable('essentials_folha_rubrica_vigencias')) {
            Schema::create('essentials_folha_rubrica_vigencias', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('business_id')->index();
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->unsignedInteger('rubrica_id');
                $table->foreign('rubrica_id')->references('id')->on('essentials_folha_rubricas')->onDelete('cascade');
                $table->char('vigencia_inicio', 7);
                $table->char('vigencia_fim', 7)->nullable();
                $table->boolean('incide_inss');
                $table->boolean('incide_irrf');
                $table->boolean('incide_fgts');
                $table->timestamps();
                $table->index(['rubrica_id', 'vigencia_inicio'], 'ess_folha_vig_rubrica_inicio_idx');
            });
        }

        if (! Schema::hasTable('essentials_folha_competencias')) {
            Schema::create('essentials_folha_competencias', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('business_id')->index();
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->char('competencia', 7);
                $table->enum('estado', ['aberta', 'fechada'])->default('aberta');
                $table->timestamps();
                $table->unique(['business_id', 'competencia'], 'ess_folha_comp_biz_comp_uq');
            });
        }

        if (! Schema::hasTable('essentials_folha_itens')) {
            Schema::create('essentials_folha_itens', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('business_id')->index();
                $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
                $table->unsignedInteger('user_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unsignedInteger('rubrica_id');
                $table->foreign('rubrica_id')->references('id')->on('essentials_folha_rubricas');
                $table->decimal('valor', 22, 4);
                $table->char('competencia_pagamento', 7);
                $table->char('competencia_apuracao', 7);
                $table->enum('proveniencia', ['ponto', 'manual', 'motor']);
                $table->string('proveniencia_ref', 64)->nullable()
                    ->comment('ponto_apuracao_dia.id, autor do lançamento ou versão da regra');
                $table->timestamps();
                $table->index(['business_id', 'competencia_pagamento'], 'ess_folha_itens_biz_pag_idx');
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('essentials_folha_itens');
        Schema::dropIfExists('essentials_folha_competencias');
        Schema::dropIfExists('essentials_folha_rubrica_vigencias');
        Schema::dropIfExists('essentials_folha_rubricas');
    }
}
