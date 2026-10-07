<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Playbook Fiscal thread 07 · operação fiscal + vigência das regras (D-OPERACAO · R-NFE-018..020d).
 *
 * Só ACRESCENTA: tabela nova + 4 colunas nulas em `nfe_fiscal_rules`. Nenhuma coluna existente
 * muda, nenhuma linha existente é alterada (append-only, ADR 0093 G8).
 *
 * - `nfe_operacoes_fiscais`: a natureza de operação (Venda, Devolução de venda...). `cfop` aceita
 *   "?" no 1º dígito (`?102` → 5/6/7 pelo destino). `regra_geral` é o Nível 4 da operação; NULL
 *   na operação padrão (Venda) quer dizer "usa o `tributacao_default` da empresa" — assim a tela
 *   ConfigDefault continua valendo, em vez de virar uma cópia órfã.
 * - `nfe_fiscal_rules.operacao_id`: NULL = regra da operação padrão (todas as regras de hoje).
 * - `valida_de`/`valida_ate`: NULL = sem limite (o comportamento de hoje).
 * - `versao_origem_id`: id da 1ª versão da cadeia — o override por produto (Nível 1) aponta para
 *   um id e precisa achar a versão vigente da mesma regra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfe_operacoes_fiscais')) {
            Schema::create('nfe_operacoes_fiscais', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->string('slug', 40);
                $table->string('nome', 80);
                // finNFe: 1 normal · 2 complementar · 3 ajuste · 4 devolução
                $table->unsignedTinyInteger('finalidade')->default(1);
                $table->char('cfop', 4)->nullable()
                    ->comment('"?" no 1º dígito = 5/6/7 conforme o destino');
                $table->string('tipo_destinatario', 20)->nullable()
                    ->comment('contribuinte · nao_contribuinte · exterior · NULL = qualquer');
                $table->json('regra_geral')->nullable()
                    ->comment('Nível 4 da operação; NULL na padrão = tributacao_default');
                $table->boolean('padrao')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['business_id', 'slug'], 'nfe_operacoes_fiscais_biz_slug_unique');
                $table->foreign('business_id', 'nfe_operacoes_fiscais_business_fk')
                    ->references('id')->on('business')->cascadeOnDelete();
            });
        }

        Schema::table('nfe_fiscal_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('nfe_fiscal_rules', 'operacao_id')) {
                $table->unsignedBigInteger('operacao_id')->nullable()->after('uf_destino')
                    ->comment('NULL = operação padrão (Venda)');
                $table->index(['business_id', 'operacao_id'], 'nfe_fiscal_rules_biz_operacao_idx');
            }
            if (! Schema::hasColumn('nfe_fiscal_rules', 'valida_de')) {
                $table->date('valida_de')->nullable()->after('operacao_id');
                $table->date('valida_ate')->nullable()->after('valida_de');
            }
            if (! Schema::hasColumn('nfe_fiscal_rules', 'versao_origem_id')) {
                $table->unsignedBigInteger('versao_origem_id')->nullable()->after('valida_ate')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('nfe_fiscal_rules', function (Blueprint $table) {
            if (Schema::hasColumn('nfe_fiscal_rules', 'operacao_id')) {
                $table->dropIndex('nfe_fiscal_rules_biz_operacao_idx');
                $table->dropColumn('operacao_id');
            }
            if (Schema::hasColumn('nfe_fiscal_rules', 'versao_origem_id')) {
                $table->dropIndex(['versao_origem_id']);
                $table->dropColumn('versao_origem_id');
            }
            if (Schema::hasColumn('nfe_fiscal_rules', 'valida_de')) {
                $table->dropColumn(['valida_de', 'valida_ate']);
            }
        });

        Schema::dropIfExists('nfe_operacoes_fiscais');
    }
};
