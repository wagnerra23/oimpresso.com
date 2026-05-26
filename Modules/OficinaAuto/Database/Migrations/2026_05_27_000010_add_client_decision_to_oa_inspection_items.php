<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `client_decision` + `client_decided_at` to `oa_inspection_items` —
 * Gap 2 PR 2b OficinaAuto US-OFICINA-035 (Wave 3b · 2026-05-27).
 *
 * Tracking item-a-item da aprovação pública do cliente via link WhatsApp
 * (DVI · Vistoria Digital): para cada item recomendado (severity=atencao|critico),
 * cliente pode aprovar/rejeitar pelo mobile na page AprovacaoDviPublica.
 *
 * - client_decision: pending (default) | approved | rejected — nullable só pra
 *   permitir migration aditiva safe em tabela com linhas pré-existentes (V0 Wave 3
 *   já tem rows sem decisão). Default 'pending' em rows novas.
 * - client_decided_at: timestamp quando cliente clicou aprovar/rejeitar.
 *   Null = ainda pendente (cliente não abriu link OU decidiu nada).
 *
 * Espelha pattern Wave 4 PR #1627 (AprovacaoOs OS-inteira) — agora granular item.
 *
 * Multi-tenant Tier 0 (ADR 0093): tabela já tem business_id + global scope.
 * Esta migration NÃO toca multi-tenancy.
 *
 * Idempotência: `Schema::hasColumn` guard pra rerun safe (padrão hotfix módulo).
 *
 * @see memory/sessions/2026-05-26-plano-gap-2-dvi-vistoria-digital-ui.md
 * @see Modules/OficinaAuto/Services/AprovacaoDviService.php
 * @see Modules/OficinaAuto/Http/Controllers/Public/AprovacaoDviController.php
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('oa_inspection_items')) {
            return; // base table ainda não criada — rodar migration mãe primeiro
        }

        Schema::table('oa_inspection_items', function (Blueprint $table) {
            if (! Schema::hasColumn('oa_inspection_items', 'client_decision')) {
                $table->enum('client_decision', ['pending', 'approved', 'rejected'])
                      ->nullable()
                      ->default('pending')
                      ->after('photo_url');
            }
            if (! Schema::hasColumn('oa_inspection_items', 'client_decided_at')) {
                $table->timestamp('client_decided_at')
                      ->nullable()
                      ->after('client_decision');
            }
        });

        // Índice composto pra query "items pendentes pra cliente no dashboard"
        // (severity + client_decision filtro frequente)
        if (Schema::hasTable('oa_inspection_items')) {
            $idxExists = collect(
                \DB::select("SHOW INDEX FROM oa_inspection_items WHERE Key_name = 'idx_oai_client_decision'")
            )->isNotEmpty();

            if (! $idxExists) {
                Schema::table('oa_inspection_items', function (Blueprint $table) {
                    $table->index(['client_decision', 'client_decided_at'], 'idx_oai_client_decision');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('oa_inspection_items')) {
            return;
        }

        Schema::table('oa_inspection_items', function (Blueprint $table) {
            $idxExists = collect(
                \DB::select("SHOW INDEX FROM oa_inspection_items WHERE Key_name = 'idx_oai_client_decision'")
            )->isNotEmpty();

            if ($idxExists) {
                $table->dropIndex('idx_oai_client_decision');
            }

            if (Schema::hasColumn('oa_inspection_items', 'client_decided_at')) {
                $table->dropColumn('client_decided_at');
            }
            if (Schema::hasColumn('oa_inspection_items', 'client_decision')) {
                $table->dropColumn('client_decision');
            }
        });
    }
};
