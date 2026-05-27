<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR 0199 — Bucket B (Opção B JSON catch-all) · pivot tabela satélite 10 cols
 * para 2 cols JSON em `contacts`. Amenda ADR 0197 §B.
 *
 * Justificativa Wagner 2026-05-27: "o período para errar é agora" — 1 cliente
 * ativo (Larissa biz=4) · 4-10 clientes legacy WR Comercial entrando · schemas
 * Delphi heterogêneos (Vargas/Gold/Vargas/Extreme/Martinho cada um custom).
 * Schema rígido (10 cols fixas em tabela satélite) escala mal contra N migrações
 * heterogêneas — JSON catch-all aceita qualquer shape sem migration por cliente.
 *
 * Schema:
 *   legacy_source ENUM('wr-comercial-delphi','outro') NULL AFTER legacy_id
 *   legacy_raw    JSON NULL                            AFTER legacy_source
 *
 * Importer canônico (próximo PR) persiste:
 *   - legacy_source = 'wr-comercial-delphi'
 *   - legacy_raw    = json_encode(PiiRedactor::redact($pessoas_firebird_row))
 *
 * Chaves JSON canônicas esperadas (documentadas em ADR 0199):
 *   - codigo_raw, data_cadastro, dt_alteracao
 *   - usuario_cadastro, usuario_alteracao
 *   - emails_extras (sub-object)
 *   - observacoes (sub-object por aba Delphi)
 *   - campos_custom_cliente (sub-object pra customizações WR por cliente)
 *   - raw_dump_pessoas_row (catch-all completo, PII redacted)
 *
 * Query forensic exemplo (storytelling "cliente desde 2003"):
 *   SELECT id, name, JSON_EXTRACT(legacy_raw, '$.data_cadastro') AS cliente_desde
 *   FROM contacts
 *   WHERE business_id = 164
 *     AND legacy_source = 'wr-comercial-delphi'
 *     AND JSON_EXTRACT(legacy_raw, '$.data_cadastro') < '2010-01-01'
 *   LIMIT 50;
 *
 * Quando virar gargalo: functional index na chave específica (review trigger
 * ADR 0199 — não premature).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL): `business_id` já existe em
 * `contacts` + indexado · JSON column herda scope automático sem ação extra.
 *
 * LGPD: legacy_raw NÃO entra em $logOnly do App\Contact (ADR 0127 §F1).
 * PII redaction obrigatória no importer ANTES de persistir aqui.
 *
 * IDEMPOTENTE — Schema::hasColumn check antes de add. Reversível via down()
 * sem perda (cols nullable, ninguém depende delas pré-existentes).
 *
 * @see memory/decisions/0199-errata-bucket-b-json-catchall-amends-0197.md
 * @see memory/decisions/0197-extend-contacts-absorcao-pessoas-legacy.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // -------------------------------------------------------------
            // legacy_source — origem da migração (enum pequeno controlado)
            // 'wr-comercial-delphi' = importer canônico WR Comercial Firebird
            // 'outro'               = futuro (Bling export, Tiny CSV, etc.)
            // NULL                  = cadastro nativo oimpresso (não migrado)
            // -------------------------------------------------------------
            if (! Schema::hasColumn('contacts', 'legacy_source')) {
                $table->enum('legacy_source', ['wr-comercial-delphi', 'outro'])
                    ->nullable();
            }

            // -------------------------------------------------------------
            // legacy_raw — catch-all JSON do dump bruto Delphi
            // Importer persiste com PII redacted (CNPJ/CPF/EMAIL/FONE).
            // Eloquent cast: array (App\Contact::$casts).
            // -------------------------------------------------------------
            if (! Schema::hasColumn('contacts', 'legacy_raw')) {
                $table->json('legacy_raw')->nullable();
            }
        });

        // Índice composto Tier 0 (business_id, legacy_source) — acelera
        // query "todos os contacts migrados do Delphi nesse business".
        // business_id PRIMEIRO sempre (ADR 0093). Try/catch porque MySQL
        // pode rejeitar ADD INDEX em sessão concorrente — não fatal.
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
            \Log::warning('contacts_bucket_b_legacy_raw_json: indice (biz, legacy_source) skip', [
                'reason' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        // Drop índice primeiro.
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
            // OK — indice já não existe
        }

        Schema::table('contacts', function (Blueprint $table) {
            foreach (['legacy_raw', 'legacy_source'] as $col) {
                if (Schema::hasColumn('contacts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
