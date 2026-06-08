<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Onda 2.5 — ADR 0170.
 *
 * Adiciona FK `payment_gateway_credential_id` em `fin_contas_bancarias`.
 *
 * NÃO REMOVE `rb_gateway_credential_id` (Onda 6 cleanup). As duas colunas
 * coexistem; controller Onda 4+ passa a escrever em ambas durante
 * transition window de 90d.
 *
 * Idempotente — checa `Schema::hasColumn` antes de adicionar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_contas_bancarias')) {
            // fin_contas_bancarias não existe nesta instância — pular (módulo Financeiro não instalado).
            return;
        }

        if (Schema::hasColumn('fin_contas_bancarias', 'payment_gateway_credential_id')) {
            return;
        }

        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->unsignedBigInteger('payment_gateway_credential_id')
                ->nullable()
                ->after('rb_gateway_credential_id');

            $table->foreign('payment_gateway_credential_id', 'fin_conta_pg_cred_fk')
                ->references('id')
                ->on('payment_gateway_credentials')
                ->nullOnDelete();

            $table->index('payment_gateway_credential_id', 'fin_conta_pg_cred_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fin_contas_bancarias')) {
            return;
        }

        if (! Schema::hasColumn('fin_contas_bancarias', 'payment_gateway_credential_id')) {
            return;
        }

        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->dropForeign('fin_conta_pg_cred_fk');
            $table->dropIndex('fin_conta_pg_cred_idx');
            $table->dropColumn('payment_gateway_credential_id');
        });
    }
};
