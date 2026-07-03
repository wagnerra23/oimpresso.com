<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * US-FISCAL-021 / ADR 0321 (PR-C) — feature flag Reforma Tributária por business.
 *
 * `reforma_tributaria_modo` gate a seleção do schema PL_010_V1 + serialização
 * grupo UB (IBS/CBS) no NfeService. Default `legacy` → schema PL_009_V4 atual,
 * XML byte-idêntico. Só business em `full`/`hybrid_2026` (opt-in explícito, pós
 * validação homologação) passa a emitir IBS/CBS. ADR ARQ-0004 `reforma_tributaria_modo`.
 *
 * Idempotente (Schema::hasColumn) + down() preserva coluna (append-only, ADR 0093 G8).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('nfe_business_configs', 'reforma_tributaria_modo')) {
            Schema::table('nfe_business_configs', function (Blueprint $table) {
                $table->enum('reforma_tributaria_modo', ['legacy', 'hybrid_2026', 'full'])
                    ->default('legacy')
                    ->after('regime')
                    ->comment('Reforma NT 2025.002: legacy=PL_009 (default, byte-idêntico) · hybrid_2026/full=PL_010 IBS/CBS (opt-in)');
            });
        }
    }

    public function down(): void
    {
        // Append-only (ADR 0093 Garantia 8): coluna preservada no rollback —
        // default 'legacy' é inerte, não quebra emissão legada.
    }
};
