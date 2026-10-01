<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aviso ao titular (LGPD Art. 18 VI) — PR-9 da thread 05 do playbook Arquivos.
 *
 * Decisão: ADR 0421 ([W] 2026-10-01 — D5 aprovada).
 *
 * Duas mudanças de schema, nenhuma destrutiva:
 *   1. `arquivos.titular_avisado_at` (nullable) — QUANDO o titular foi avisado. NULL = não
 *      avisado. Só o `AvisoTitularService::registrarAviso()` escreve aqui.
 *   2. `notice` no enum de `arquivos_audit_log.action` — 3º alargamento (os anteriores são
 *      2026_07_02_000001 e 2026_08_10_000001). Mesma convenção: MySQL-only, só AMPLIA.
 *
 * Avisar NÃO apaga nada e NÃO dispara purge: a coluna é um carimbo de data.
 *
 * Idempotente: `hasColumn` antes de criar; o MODIFY com o mesmo alvo é no-op no MySQL.
 * O `down()` recusa reverter se já houver aviso gravado — perder a prova de que o titular
 * foi avisado é pior que reversão bloqueada (trilha append-only, ADR 0123 §8).
 *
 * Multi-tenant (ADR 0093): a coluna vive na linha do arquivo, que já carrega `business_id`;
 * o registro na trilha grava o `business_id` do próprio arquivo.
 *
 * @see Modules/Arquivos/Services/AvisoTitularService.php
 * @see memory/decisions/0421-arquivos-aviso-ao-titular-registro-e-janela.md
 */
return new class extends Migration
{
    private const ENUM_WIDE = "'upload','download','classify','reclassify','soft_delete','restore','hard_delete','signed_url_issued','signed_url_consumed','exported','notice'";

    private const ENUM_NARROW = "'upload','download','classify','reclassify','soft_delete','restore','hard_delete','signed_url_issued','signed_url_consumed','exported'";

    public function up(): void
    {
        if (Schema::hasTable('arquivos') && ! Schema::hasColumn('arquivos', 'titular_avisado_at')) {
            Schema::table('arquivos', function (Blueprint $table) {
                $table->timestamp('titular_avisado_at')->nullable()->after('retention_days');
            });
        }

        // Enum: MySQL-only (SQLite não tem MODIFY COLUMN) — mesma convenção dos widen anteriores.
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('arquivos_audit_log')) {
            return;
        }

        DB::statement(
            'ALTER TABLE arquivos_audit_log MODIFY COLUMN action ENUM(' . self::ENUM_WIDE . ') NOT NULL'
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('arquivos') && Schema::hasColumn('arquivos', 'titular_avisado_at')) {
            $avisados = DB::table('arquivos')->whereNotNull('titular_avisado_at')->count();
            if ($avisados > 0) {
                throw new \RuntimeException(
                    "Reversão bloqueada: {$avisados} arquivo(s) com titular_avisado_at. "
                    . 'Apagar a coluna destruiria a prova do aviso (ADR 0421).'
                );
            }
        }

        if (DB::connection()->getDriverName() === 'mysql' && Schema::hasTable('arquivos_audit_log')) {
            $notices = DB::table('arquivos_audit_log')->where('action', 'notice')->count();
            if ($notices > 0) {
                throw new \RuntimeException(
                    "Reversão bloqueada: {$notices} linha(s) 'notice' em arquivos_audit_log. "
                    . 'Estreitar o enum orfanaria audit append-only (ADR 0123 §8).'
                );
            }

            DB::statement(
                'ALTER TABLE arquivos_audit_log MODIFY COLUMN action ENUM(' . self::ENUM_NARROW . ') NOT NULL'
            );
        }

        if (Schema::hasTable('arquivos') && Schema::hasColumn('arquivos', 'titular_avisado_at')) {
            Schema::table('arquivos', function (Blueprint $table) {
                $table->dropColumn('titular_avisado_at');
            });
        }
    }
};
