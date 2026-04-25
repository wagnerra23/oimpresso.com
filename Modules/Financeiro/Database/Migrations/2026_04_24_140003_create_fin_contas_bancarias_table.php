<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Complemento 1-1 da tabela `accounts` (core UltimatePOS).
 *
 * Decisão: ADR ARQ-0001 (módulo isolado, não monkey-patch core) +
 * memory/requisitos/Financeiro/adr/tech/0003-mvp-eduardokum-com-mock-cnab.md
 *
 * Larissa cadastra a conta no admin POS (`accounts`); quando precisa emitir
 * boleto daquela conta, vai em /financeiro/contas-bancarias e preenche
 * carteira/convênio/cedente/beneficiário aqui. Sem duplicar nome/saldo do core.
 */
class CreateFinContasBancariasTable extends Migration
{
    public function up(): void
    {
        Schema::create('fin_contas_bancarias', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('business_id')->unsigned()->index();
            $table->integer('account_id')->unsigned()->unique()
                  ->comment('FK 1-1 accounts.id (core UltimatePOS)');

            // Identificação bancária
            $table->char('banco_codigo', 3)->index()
                  ->comment('FEBRABAN: 001=BB, 033=Santander, 104=Caixa, 237=Bradesco, 341=Itau, 748=Sicredi, 756=Bancoob, ...');
            $table->string('agencia', 10);
            $table->char('agencia_dv', 2)->nullable();
            $table->char('conta_dv', 2)->nullable()
                  ->comment('Numero da conta vem de accounts.account_number; aqui só o digito separado');

            // Específico boleto
            $table->string('carteira', 10)
                  ->comment('Carteira CNAB — depende do banco (ex: BB=18, Itau=109, Sicoob=1)');
            $table->string('convenio', 30)->nullable()
                  ->comment('Convenio CNAB (BB/Sicoob/Caixa) — null para bancos sem');
            $table->string('codigo_cedente', 30)->nullable()
                  ->comment('Codigo cedente / beneficiário no banco — alguns bancos pedem');
            $table->string('variacao_carteira', 10)->nullable();

            // Beneficiário (PJ que emite o boleto)
            $table->string('beneficiario_documento', 18)
                  ->comment('CPF ou CNPJ formatado (XX.XXX.XXX/XXXX-XX)');
            $table->string('beneficiario_razao_social', 150);
            $table->string('beneficiario_logradouro', 150)->nullable();
            $table->string('beneficiario_bairro', 80)->nullable();
            $table->string('beneficiario_cidade', 80)->nullable();
            $table->char('beneficiario_uf', 2)->nullable();
            $table->char('beneficiario_cep', 9)->nullable()->comment('Formato XXXXX-XXX');

            // Certificado A1 (alguns bancos exigem para registro online; null = uso CNAB tradicional)
            $table->string('certificado_path', 255)->nullable()
                  ->comment('Caminho relativo ao storage; null em modo mock');
            $table->string('certificado_password_encrypted', 255)->nullable();

            // Operacional
            $table->boolean('ativo_para_boleto')->default(true);
            $table->json('metadata')->nullable()
                  ->comment('Specifics por banco (ex: PIX dict_key, webhook_url) — shape livre');

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->index(['business_id', 'ativo_para_boleto'], 'idx_biz_ativo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_contas_bancarias');
    }
}
