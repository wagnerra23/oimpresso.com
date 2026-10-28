<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regras de conciliação automática (US-FIN-080 · UC-FCC-19..22).
 *
 * Uma regra diz "linha do extrato com descrição que contém X, nesse sentido, nessa faixa de
 * valor → é título daquele cliente / plano de contas / categoria". O motor
 * (`RegrasConciliacaoService`) só aplica a regra quando ela deixa UM título aberto de mesmo
 * valor; com zero ou mais de um, a linha segue para o casamento por valor e data de sempre.
 *
 * `acao`: `sugerir` deixa a linha como sugerida (o usuário confirma); `conciliar` já confirma.
 * Nada disso roda com `financeiro.conciliacao.regras_enabled` desligado.
 *
 * `regra_id` nas duas tabelas de extrato guarda qual regra casou a linha (trilha + reabrir).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_conciliacao_regras')) {
            Schema::create('fin_conciliacao_regras', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('business_id');
                $t->string('nome', 120);
                $t->boolean('ativo')->default(true);
                $t->unsignedSmallInteger('prioridade')->default(100)->comment('menor roda primeiro');
                $t->string('descricao_contem', 120)->nullable()->comment('trecho da descrição, sem diferenciar maiúscula');
                $t->char('sentido', 1)->nullable()->comment('C = entrada, D = saída; nulo = qualquer');
                $t->decimal('valor_min', 15, 2)->nullable();
                $t->decimal('valor_max', 15, 2)->nullable();
                $t->unsignedInteger('conta_bancaria_id')->nullable();
                $t->unsignedInteger('cliente_id')->nullable()->comment('contacts.id do título alvo');
                $t->unsignedInteger('plano_conta_id')->nullable();
                $t->unsignedInteger('categoria_id')->nullable();
                $t->string('acao', 12)->default('sugerir')->comment('sugerir | conciliar');
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
                $t->softDeletes();

                $t->index(['business_id', 'ativo', 'prioridade'], 'fin_concil_regras_biz_ativo_idx');
                $t->foreign('business_id', 'fin_concil_regras_biz_fk')
                    ->references('id')->on('business')->cascadeOnDelete();
            });
        }

        foreach (['fin_bank_statement_lines', 'fin_extrato_lancamentos'] as $tabela) {
            if (Schema::hasTable($tabela) && ! Schema::hasColumn($tabela, 'regra_id')) {
                Schema::table($tabela, function (Blueprint $t) {
                    $t->unsignedInteger('regra_id')->nullable()->after('match_score')
                        ->comment('fin_conciliacao_regras.id que casou a linha (US-FIN-080)');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['fin_bank_statement_lines', 'fin_extrato_lancamentos'] as $tabela) {
            if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, 'regra_id')) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropColumn('regra_id'));
            }
        }
        Schema::dropIfExists('fin_conciliacao_regras');
    }
};
