<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 da [ADR 0236] — unificação extrato OFX → fin_extrato_lancamentos.
 *
 * Adiciona as colunas que permitem a tabela canônica abrigar TAMBÉM o extrato
 * OFX (hoje em fin_bank_statement_lines):
 *   - `origem`       enum api|ofx|manual (default 'api' — preserva linhas existentes)
 *   - `source_file`  nome do arquivo OFX (NULL pra linhas API)
 *   - `external_id`  chave anti-duplicata unificada: "ofx:<fitid>" / "api:<idempotency_key>"
 *
 * Novo UNIQUE (business_id, conta_bancaria_id, external_id) serve as duas origens
 * via prefixo (DD-1 do plano). É ADITIVO + idempotente: backfill das linhas API
 * existentes preenche external_id="api:".idempotency_key (no Command, não aqui —
 * migration só cria estrutura). NÃO altera o UNIQUE antigo
 * (conta_bancaria_id, idempotency_key) — convivem durante a transição.
 *
 * @see memory/decisions/0236-extrato-conciliacao-modelo-unificado.md
 * @see memory/requisitos/Financeiro/PLANO-FASE2-MIGRACAO-EXTRATO-UNIFICADO.md
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fin_extrato_lancamentos')) {
            return;
        }

        Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
            if (! Schema::hasColumn('fin_extrato_lancamentos', 'origem')) {
                $table->enum('origem', ['api', 'ofx', 'manual'])
                    ->default('api')
                    ->after('conta_bancaria_id')
                    ->comment('Fase 2 ADR 0236 — origem do dado de extrato');
            }
            if (! Schema::hasColumn('fin_extrato_lancamentos', 'source_file')) {
                $table->string('source_file', 255)->nullable()->after('descricao')
                    ->comment('nome do arquivo OFX quando origem=ofx');
            }
            if (! Schema::hasColumn('fin_extrato_lancamentos', 'external_id')) {
                // nullable nesta migration: backfill (Command) preenche as linhas
                // API existentes antes do UNIQUE virar enforce-real no fluxo.
                $table->string('external_id', 160)->nullable()->after('idempotency_key')
                    ->comment('chave unificada: ofx:<fitid> / api:<idempotency_key>');
            }
        });

        // Index não-único primeiro (busca por external_id) — barato e seguro.
        $hasIdx = collect(Schema::getIndexes('fin_extrato_lancamentos'))
            ->contains(fn ($i) => $i['name'] === 'fin_extrato_external_id_idx');
        if (! $hasIdx) {
            Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
                $table->index(['business_id', 'external_id'], 'fin_extrato_external_id_idx');
            });
        }

        // O UNIQUE (business_id, conta_bancaria_id, external_id) NÃO é criado aqui:
        // exige external_id preenchido em TODAS as linhas (senão múltiplos NULL
        // colidem em alguns engines). É criado numa migration separada da Fase 2
        // APÓS o backfill rodar (financeiro:backfill-extrato-ofx). Mantém esta
        // migration 100% segura de rodar em prod antes do backfill.
    }

    public function down(): void
    {
        if (! Schema::hasTable('fin_extrato_lancamentos')) {
            return;
        }

        Schema::table('fin_extrato_lancamentos', function (Blueprint $table) {
            $hasIdx = collect(Schema::getIndexes('fin_extrato_lancamentos'))
                ->contains(fn ($i) => $i['name'] === 'fin_extrato_external_id_idx');
            if ($hasIdx) {
                $table->dropIndex('fin_extrato_external_id_idx');
            }

            foreach (['external_id', 'source_file', 'origem'] as $col) {
                if (Schema::hasColumn('fin_extrato_lancamentos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
