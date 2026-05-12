<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estende ENUM transaction_documents.doc_type pra cobrir cobrança financeira
 * via gateways (Asaas / Inter PJ).
 *
 * Schema original (2026_05_11_140001_create_transaction_documents_table.php):
 *   ENUM('nfe55','nfce65','nfse56','nfcom62','mdfe58','cte57')
 *
 * Schema novo:
 *   ENUM('nfe55','nfce65','nfse56','nfcom62','mdfe58','cte57',
 *        'boleto_asaas','boleto_inter')
 *
 * Por que estender ENUM (vs converter pra VARCHAR):
 *   - Append-only é compatível com migration MySQL safe (não-bloqueante quando lista cresce)
 *   - Mantém invariante de "tipo conhecido" — Eloquent + EstornarBoletoJob validam
 *     contra constantes do Model (DOC_BOLETO_ASAAS, DOC_BOLETO_INTER)
 *   - VARCHAR perderia validação de domínio no schema (Tier 0 — schema é contrato)
 *
 * Pré-requisito da US-CASCADE-BOLETO-001 (PR foundational):
 *   - Permite registrar cobranças Asaas/Inter como documentos polimórficos
 *   - EstornarBoletoJob despacha pra Cancelar*Job por gateway específico
 *   - Integração com CancelarVendaCascade fica na US-CASCADE-BOLETO-002 (próxima)
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) preservado — coluna business_id
 * inalterada, scope continua via HasBusinessScope.
 *
 * Idempotente: checa INFORMATION_SCHEMA.COLUMNS antes de ALTER. Rerun safe.
 * Rollback bloqueado se houver rows com novos valores (proteção contra perda).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_documents')) {
            return;
        }

        $current = DB::selectOne(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'transaction_documents'
               AND COLUMN_NAME = 'doc_type'"
        );

        if ($current === null) {
            return;
        }

        // Idempotência — já contém ambos os novos valores
        if (str_contains((string) $current->COLUMN_TYPE, "'boleto_asaas'")
            && str_contains((string) $current->COLUMN_TYPE, "'boleto_inter'")) {
            return;
        }

        DB::statement(
            "ALTER TABLE transaction_documents MODIFY COLUMN doc_type ENUM(
                'nfe55',
                'nfce65',
                'nfse56',
                'nfcom62',
                'mdfe58',
                'cte57',
                'boleto_asaas',
                'boleto_inter'
            ) NOT NULL"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('transaction_documents')) {
            return;
        }

        // Bloqueia rollback se existirem rows com novos doc_types — evita perda silenciosa
        $rowsBoleto = DB::table('transaction_documents')
            ->whereIn('doc_type', ['boleto_asaas', 'boleto_inter'])
            ->count();

        if ($rowsBoleto > 0) {
            throw new RuntimeException(
                "Cannot rollback: {$rowsBoleto} row(s) em transaction_documents com doc_type "
                . "'boleto_asaas' ou 'boleto_inter'. Migrar/remover antes de rodar down()."
            );
        }

        DB::statement(
            "ALTER TABLE transaction_documents MODIFY COLUMN doc_type ENUM(
                'nfe55',
                'nfce65',
                'nfse56',
                'nfcom62',
                'mdfe58',
                'cte57'
            ) NOT NULL"
        );
    }
};
