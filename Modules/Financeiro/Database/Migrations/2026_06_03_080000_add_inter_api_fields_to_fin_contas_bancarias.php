<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos pra integração API Inter v3 (cobrança + webhook).
 *
 * Decisão: ADR ARQ-0003 + memory/requisitos/Financeiro/adr/tech/0004-inter-api-v3.md
 * (criar junto com este PR).
 *
 * Por que colunas dedicadas em vez de metadata JSON:
 *  - client_id/client_secret precisam de cast 'encrypted' por LGPD/segurança
 *  - webhook_token precisa de UNIQUE pra lookup O(1) na rota
 *  - webhook_registered_at é auditável (LGPD Art. 37)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->string('certificado_chave_path', 255)->nullable()->after('certificado_path')
                ->comment('Path da chave privada (.key) — Inter mTLS exige par cert+key');
            $table->text('inter_client_id_encrypted')->nullable()->after('certificado_password_encrypted');
            $table->text('inter_client_secret_encrypted')->nullable()->after('inter_client_id_encrypted');
            $table->string('webhook_token', 64)->nullable()->unique()->after('inter_client_secret_encrypted')
                ->comment('Token randômico no path da URL webhook. Sem auth — segredo é o token.');
            $table->timestamp('webhook_registered_at')->nullable()->after('webhook_token')
                ->comment('Quando rodou financeiro:inter:registrar-webhook com sucesso');
        });
    }

    public function down(): void
    {
        Schema::table('fin_contas_bancarias', function (Blueprint $table) {
            $table->dropColumn([
                'certificado_chave_path',
                'inter_client_id_encrypted',
                'inter_client_secret_encrypted',
                'webhook_token',
                'webhook_registered_at',
            ]);
        });
    }
};
