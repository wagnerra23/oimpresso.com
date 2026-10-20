<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FIN-072 · UC-FLX-30 — valor do ISS retido gravado na emissão da NFS-e.
 *
 * Até aqui só existia o flag `iss_retido`, e o `valor_iss` grava 0 quando o ISS é retido
 * (`NfseEmissaoPayload::valorIss()`). O Financeiro precisa do valor para cobrar o líquido.
 * Nullable: nota anterior a esta migração fica NULL e o leitor deriva de
 * `valor_servicos × aliquota_iss` (mesma conta do payload).
 *
 * Mora no NfeBrasil por D-NFSE-CONSOLIDA, como as retenções federais (2026_10_13_000001).
 * Idempotente (Schema::hasColumn) + down() preserva a coluna (append-only, ADR 0093 G8).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nfse_emissoes') || Schema::hasColumn('nfse_emissoes', 'valor_iss_retido')) {
            return;
        }

        Schema::table('nfse_emissoes', function (Blueprint $table) {
            $table->decimal('valor_iss_retido', 15, 2)->nullable()
                ->comment('ISS retido pelo tomador (0 quando não retido)');
        });
    }

    public function down(): void
    {
        // Append-only (ADR 0093 Garantia 8): coluna preservada — NULL é inerte.
    }
};
