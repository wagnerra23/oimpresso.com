<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anexos da OS pelo app das lojas (P2, PR 09 do playbook propostas-app).
 *
 * Contrato: memory/requisitos/AppMobile/api/tela-03-anexos.md — cada anexo tem `legenda` (o que o
 * mecânico escreveu) e `etapa` (a etapa da OS no momento do envio, gravada pelo servidor). As duas
 * colunas são nulas: os arquivos que já existem (NFe XML, anexos da web) seguem sem elas.
 *
 * Multi-tenant (ADR 0093): a coluna vive na linha do arquivo, que já carrega `business_id`.
 * Idempotente: re-rodar não falha.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arquivos', function (Blueprint $table) {
            if (! Schema::hasColumn('arquivos', 'legenda')) {
                $table->string('legenda', 160)->nullable()->after('original_name');
            }
            if (! Schema::hasColumn('arquivos', 'etapa')) {
                $table->string('etapa', 80)->nullable()->after('legenda');
            }
        });
    }

    public function down(): void
    {
        Schema::table('arquivos', function (Blueprint $table) {
            foreach (['etapa', 'legenda'] as $coluna) {
                if (Schema::hasColumn('arquivos', $coluna)) {
                    $table->dropColumn($coluna);
                }
            }
        });
    }
};
