<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0200 — `contacts` adopta canon sync bidirecional Wagner 2024-11.
 *
 * Amenda ADR 0197 + ADR 0199. Consolida pattern Bucket A+B (legacy_id +
 * legacy_source + legacy_raw) com canon Wagner estabelecido em 11 tabelas
 * (brands/products/users/categories/units/condicaopagto/cidades/pessoas_grupo/
 * nf_natureza_operacao/produto_grupo/nf_natureza_operacao_prodgrupo) entre
 * 2024-11-11 e 2025-01-06.
 *
 * Schema canon (idêntico a `add_sync_fields_to_products_table` 2025-01-06):
 *   officeimpresso_codigo       VARCHAR(255) NULL  -- CODIGO Delphi (sync FK)
 *   officeimpresso_dt_alteracao TIMESTAMP NULL     -- DT_ALTERACAO Delphi
 *
 * Integra `Modules/Connector/Http/Controllers/Api/BaseApiController::syncData`
 * automaticamente — last-write-wins com conflict detection (linha 67-73).
 *
 * Limpeza:
 *   - DROP legacy_source ENUM (criada hoje em PR #1731 ADR 0199 às 13:50 BRT)
 *     -- redundante com officeimpresso_codigo IS NOT NULL (indica origem Delphi)
 *   - DROP indice idx_contacts_biz_legacy_source (criado pareada)
 *
 * Preservados (propósitos distintos, zero redundância):
 *   - legacy_id (VARCHAR 32) — CNPJ normalizado pra dedup importer one-shot
 *   - legacy_raw (JSON) — dump bruto Delphi pra forensics LGPD + storytelling UI
 *
 * 4 campos finais em contacts (clareza pro time):
 *   - legacy_id                 → match dedup importer Python
 *   - officeimpresso_codigo     → sync bidirecional viva (BaseApiController)
 *   - officeimpresso_dt_alteracao → conflict detection
 *   - legacy_raw                → forensics + storytelling
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *   Indice composto (business_id, officeimpresso_codigo) preserva scope.
 *
 * IDEMPOTENTE — Schema::hasColumn / SHOW INDEXES check antes de cada op.
 * Reversível via down() sem perda de dados (legacy_source vazia em prod).
 *
 * @see memory/decisions/0200-contacts-sync-canon-amends-0197-0199.md
 * @see memory/decisions/0197-extend-contacts-absorcao-pessoas-legacy.md
 * @see memory/decisions/0199-errata-bucket-b-json-catchall-amends-0197.md
 * @see Modules/Connector/Http/Controllers/Api/BaseApiController.php
 */
return new class extends Migration
{
    public function up(): void
    {
        // --------------------------------------------------------------------
        // ADD canon Wagner 2024-11 (alinha com products/brands/users/+8 tabelas)
        // --------------------------------------------------------------------
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'officeimpresso_codigo')) {
                $table->string('officeimpresso_codigo', 255)->nullable();
            }

            if (! Schema::hasColumn('contacts', 'officeimpresso_dt_alteracao')) {
                $table->timestamp('officeimpresso_dt_alteracao')->nullable();
            }
        });

        // Indice composto Tier 0 — business_id PRIMEIRO sempre (ADR 0093).
        // Acelera GET /connector/api/contacts/sync por CODIGO Delphi.
        try {
            Schema::table('contacts', function (Blueprint $table) {
                $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                    ->pluck('Key_name')
                    ->toArray();

                if (! in_array('idx_contacts_biz_officeimpresso_codigo', $existing, true)) {
                    $table->index(
                        ['business_id', 'officeimpresso_codigo'],
                        'idx_contacts_biz_officeimpresso_codigo'
                    );
                }
            });
        } catch (\Throwable $e) {
            \Log::warning('contacts_consolidate_officeimpresso_sync_canon: ADD idx skip', [
                'reason' => $e->getMessage(),
            ]);
        }

        // --------------------------------------------------------------------
        // DROP legacy_source (criada hoje em PR #1731 às 13:50 BRT, ADR 0199).
        // Redundante: officeimpresso_codigo IS NOT NULL indica origem Delphi.
        // Zero dados em prod (nenhum importer rodou ainda).
        // --------------------------------------------------------------------
        try {
            Schema::table('contacts', function (Blueprint $table) {
                $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                    ->pluck('Key_name')
                    ->toArray();

                if (in_array('idx_contacts_biz_legacy_source', $existing, true)) {
                    $table->dropIndex('idx_contacts_biz_legacy_source');
                }
            });
        } catch (\Throwable $e) {
            // Indice já não existe — ok
        }

        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'legacy_source')) {
                $table->dropColumn('legacy_source');
            }
        });
    }

    public function down(): void
    {
        // Restaurar legacy_source (mergeada em PR #1731 ADR 0199).
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'legacy_source')) {
                $table->enum('legacy_source', ['wr-comercial-delphi', 'outro'])
                    ->nullable()
                    ->after('legacy_id');
            }
        });

        try {
            Schema::table('contacts', function (Blueprint $table) {
                $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                    ->pluck('Key_name')
                    ->toArray();

                if (! in_array('idx_contacts_biz_legacy_source', $existing, true)) {
                    $table->index(
                        ['business_id', 'legacy_source'],
                        'idx_contacts_biz_legacy_source'
                    );
                }
            });
        } catch (\Throwable $e) {
            // ok
        }

        // Drop canon Wagner cols + indice composto.
        try {
            Schema::table('contacts', function (Blueprint $table) {
                $existing = collect(DB::select('SHOW INDEXES FROM contacts'))
                    ->pluck('Key_name')
                    ->toArray();

                if (in_array('idx_contacts_biz_officeimpresso_codigo', $existing, true)) {
                    $table->dropIndex('idx_contacts_biz_officeimpresso_codigo');
                }
            });
        } catch (\Throwable $e) {
            // ok
        }

        Schema::table('contacts', function (Blueprint $table) {
            foreach (['officeimpresso_dt_alteracao', 'officeimpresso_codigo'] as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
