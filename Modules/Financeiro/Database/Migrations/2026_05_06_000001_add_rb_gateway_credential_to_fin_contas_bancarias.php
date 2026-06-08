<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('fin_contas_bancarias', 'rb_gateway_credential_id')) {
            return;
        }

        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->unsignedBigInteger('rb_gateway_credential_id')
                ->nullable()
                ->after('ativo_para_boleto')
                ->comment('FK para rb_boleto_credentials — null quando conta não tem cobrança ativa');

            // A constraint FK só é criada se a tabela alvo já existe. Em
            // `migrate` do-zero (CI fresh), Financeiro roda ANTES de
            // RecurringBilling (mesmo timestamp 2026_05_06_000001, ordem
            // alfabética por módulo) → `rb_boleto_credentials` ainda não existe.
            // Guard evita erro 1824 (referenced table) sem perder a coluna.
            // Em prod/dogfood a tabela já existe → FK criada normalmente.
            if (Schema::hasTable('rb_boleto_credentials')) {
                $table->foreign('rb_gateway_credential_id', 'fin_conta_rb_cred_fk')
                    ->references('id')
                    ->on('rb_boleto_credentials')
                    ->nullOnDelete();
            }

            $table->index('rb_gateway_credential_id', 'fin_conta_rb_cred_idx');
        });
    }

    public function down(): void
    {
        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->dropForeign('fin_conta_rb_cred_fk');
            $table->dropIndex('fin_conta_rb_cred_idx');
            $table->dropColumn('rb_gateway_credential_id');
        });
    }
};
