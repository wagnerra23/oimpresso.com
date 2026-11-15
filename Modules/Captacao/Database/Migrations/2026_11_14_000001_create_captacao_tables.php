<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fundo do Modules/Captacao (ADR 0452, onda 1).
 *
 * Regras fixadas no banco:
 *  - Toda tabela tem `business_id` NOT NULL, indexado e com FK (ADR 0093).
 *  - Proponente é texto no projeto (nome + CNPJ), sem FK para outro módulo (D3).
 *  - Doador é `contact` do núcleo, opcional (D8). Voluntário não tem cadastro: aporte de horas.
 *  - Aporte e comprovante não se apagam: saem da lista com `removido_em` + motivo (D4).
 *  - Valores em decimal(22,4), o padrão do núcleo. Nenhum cálculo de total nesta onda.
 */
return new class extends Migration
{
    private const TABELAS = ['captacao_comprovantes', 'captacao_aportes', 'captacao_projetos', 'captacao_fontes'];

    public function up(): void
    {
        if (! Schema::hasTable('captacao_fontes')) {
            Schema::create('captacao_fontes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('tipo', 20)->comment('edital|lei_incentivo|fundo|doacao_pj|doacao_pf|parceria|voluntariado');
                $t->string('nome', 191);
                $t->string('orgao', 191)->nullable();
                $t->string('numero_edital', 60)->nullable();
                $t->date('inscricao_ate')->nullable();
                $t->text('observacao')->nullable();
                $t->boolean('ativa')->default(true);
                $t->timestamps();
                $t->unique(['business_id', 'nome'], 'capt_fonte_biz_nome_unique');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('captacao_projetos')) {
            Schema::create('captacao_projetos', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->string('titulo', 191);
                $t->text('objetivo')->nullable();
                $t->string('proponente_nome', 191);
                $t->string('proponente_cnpj', 18)->nullable();
                $t->string('situacao', 20)->default('ideia')
                    ->comment('ideia|elaboracao|inscrito|aprovado|reprovado|captando|execucao|prestacao_contas|encerrado');
                $t->date('situacao_desde')->nullable();
                $t->decimal('valor_solicitado', 22, 4)->nullable();
                $t->decimal('valor_aprovado', 22, 4)->nullable();
                $t->date('execucao_inicio')->nullable();
                $t->date('execucao_fim')->nullable();
                $t->date('prestacao_contas_ate')->nullable();
                $t->unsignedInteger('created_by')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'situacao'], 'capt_proj_biz_sit_idx');
                $t->foreign('business_id')->references('id')->on('business');
            });
        }

        if (! Schema::hasTable('captacao_aportes')) {
            Schema::create('captacao_aportes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('projeto_id');
                $t->unsignedBigInteger('fonte_id')->nullable();
                $t->string('natureza', 20)->comment('financeiro|bens|servicos|horas_voluntarias');
                $t->unsignedInteger('contact_id')->nullable()->comment('doador, quando houver (D8)');
                $t->string('descricao', 255)->nullable();
                $t->decimal('valor', 22, 4)->default(0)->comment('em horas_voluntarias, bens e servicos: valor estimado');
                $t->decimal('horas', 10, 2)->nullable();
                $t->date('recebido_em');
                $t->dateTime('removido_em')->nullable();
                $t->string('removido_motivo', 255)->nullable();
                $t->unsignedInteger('removido_por')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'projeto_id'], 'capt_aporte_biz_proj_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('projeto_id')->references('id')->on('captacao_projetos');
                $t->foreign('fonte_id')->references('id')->on('captacao_fontes');
                $t->foreign('contact_id')->references('id')->on('contacts');
            });
        }

        if (! Schema::hasTable('captacao_comprovantes')) {
            Schema::create('captacao_comprovantes', function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id')->index();
                $t->unsignedBigInteger('projeto_id');
                $t->unsignedBigInteger('aporte_id')->nullable()->comment('recurso que pagou a despesa, quando se sabe');
                $t->string('descricao', 255);
                $t->string('rubrica', 120)->nullable();
                $t->decimal('valor', 22, 4);
                $t->date('pago_em');
                $t->unsignedBigInteger('arquivo_id')->nullable()->comment('arquivo no Modules/Arquivos');
                $t->dateTime('removido_em')->nullable();
                $t->string('removido_motivo', 255)->nullable();
                $t->unsignedInteger('removido_por')->nullable();
                $t->timestamps();
                $t->index(['business_id', 'projeto_id'], 'capt_compr_biz_proj_idx');
                $t->foreign('business_id')->references('id')->on('business');
                $t->foreign('projeto_id')->references('id')->on('captacao_projetos');
                $t->foreign('aporte_id')->references('id')->on('captacao_aportes');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::dropIfExists($tabela);
        }
    }
};
