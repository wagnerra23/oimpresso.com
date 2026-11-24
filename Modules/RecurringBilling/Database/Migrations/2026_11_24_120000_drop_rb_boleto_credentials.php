<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 5c da saída de `rb_boleto_credentials` (decisão b1, ADR 0456, 2026-11-24).
 *
 * Apaga `fin_contas_bancarias.rb_gateway_credential_id` (FK + índice) e a tabela
 * `rb_boleto_credentials`. Depois das ondas 1 a 5b nenhum código lê nenhum dos dois: a
 * credencial de gateway é a do PaymentGateway (`payment_gateway_credentials`), copiada
 * pela migration `2026_11_23_120000_copia_credenciais_rb_para_payment_gateway`, que roda
 * antes desta.
 *
 * Medido em produção antes da onda: a tabela tinha 0 linhas e nenhuma conta apontava
 * para ela. Mesmo assim a migration RECUSA apagar se a tabela tiver alguma linha: o
 * deploy falha visível em vez de perder credencial em silêncio. Com linha, a saída é
 * conferir a cópia no PaymentGateway e só então esvaziar a tabela.
 *
 * down() recria a estrutura vazia (sem dado): o que foi apagado não volta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rb_boleto_credentials')) {
            $linhas = DB::table('rb_boleto_credentials')->count();
            if ($linhas > 0) {
                throw new RuntimeException(
                    "rb_boleto_credentials tem {$linhas} linha(s); confira a cópia em payment_gateway_credentials antes de apagar."
                );
            }
        }

        if (Schema::hasTable('fin_contas_bancarias') && Schema::hasColumn('fin_contas_bancarias', 'rb_gateway_credential_id')) {
            $temFk = collect(Schema::getForeignKeys('fin_contas_bancarias'))
                ->contains(fn ($fk) => ($fk['name'] ?? null) === 'fin_conta_rb_cred_fk');
            $temIndice = collect(Schema::getIndexes('fin_contas_bancarias'))
                ->contains(fn ($i) => ($i['name'] ?? null) === 'fin_conta_rb_cred_idx');

            Schema::table('fin_contas_bancarias', function (Blueprint $table) use ($temFk, $temIndice) {
                if ($temFk) {
                    $table->dropForeign('fin_conta_rb_cred_fk');
                }
                if ($temIndice) {
                    $table->dropIndex('fin_conta_rb_cred_idx');
                }
            });

            Schema::table('fin_contas_bancarias', function (Blueprint $table) {
                $table->dropColumn('rb_gateway_credential_id');
            });
        }

        Schema::dropIfExists('rb_boleto_credentials');
    }

    public function down(): void
    {
        if (! Schema::hasTable('rb_boleto_credentials')) {
            Schema::create('rb_boleto_credentials', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('conta_bancaria_id')->nullable()->index('rb_boleto_cred_conta_idx');
                $table->enum('banco', ['inter', 'c6', 'asaas']);
                $table->enum('ambiente', ['production', 'sandbox'])->default('production');
                $table->boolean('ativo')->default(true)->index();
                $table->string('nome_display')->nullable();
                $table->json('config_json');
                $table->timestamps();

                $table->unique(['business_id', 'banco'], 'rb_boleto_cred_biz_banco_unique');
            });
        }

        if (Schema::hasTable('fin_contas_bancarias') && ! Schema::hasColumn('fin_contas_bancarias', 'rb_gateway_credential_id')) {
            Schema::table('fin_contas_bancarias', function (Blueprint $table) {
                $table->unsignedBigInteger('rb_gateway_credential_id')->nullable()->after('ativo_para_boleto');
                $table->index('rb_gateway_credential_id', 'fin_conta_rb_cred_idx');
            });
        }
    }
};
