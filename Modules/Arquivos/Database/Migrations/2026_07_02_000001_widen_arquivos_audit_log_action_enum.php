<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amplia o enum `arquivos_audit_log.action` com `signed_url_consumed`.
 *
 * Bug (code review adversarial 2026-07-02): DownloadController grava a action
 * `signed_url_consumed` ao consumir uma signed URL, mas o enum original
 * (2026_05_10_000002) só aceitava até `signed_url_issued`. Em MySQL strict mode
 * o INSERT falhava e era engolido pelo try/catch do controller — NENHUMA
 * consumação de signed URL era auditada (viola ADR 0123 §8 "Audit log integral").
 *
 * `signed_url_issued` (link gerado, payload {expires_minutes}) e
 * `signed_url_consumed` (link baixado, payload {ip, agent}) são eventos
 * distintos do ciclo de vida — o detector anti-scraping do
 * `arquivos:audit-log --suspicious` depende justamente dos eventos consumed
 * (que carregam IP).
 *
 * Append-only (ADR 0123 §8): esta migration só AMPLIA o enum. O down() recusa
 * estreitar se já houver linhas `signed_url_consumed` gravadas (não órfã audit).
 *
 * @see Modules/Arquivos/Http/Controllers/DownloadController.php
 * @see Modules/Arquivos/Console/Commands/AuditLogCommand.php (detector rapid-fire)
 * @see memory/decisions/0123-modules-arquivos-backbone.md §8
 */
return new class extends Migration {
    private const ENUM_WIDE = "'upload','download','classify','reclassify','soft_delete','restore','hard_delete','signed_url_issued','signed_url_consumed'";

    private const ENUM_NARROW = "'upload','download','classify','reclassify','soft_delete','restore','hard_delete','signed_url_issued'";

    public function up(): void
    {
        // MySQL-only: SQLite (lane de teste reduzida) não tem MODIFY COLUMN e o bug
        // de ENUM strict só existe no MySQL. Convenção idêntica às enum-migrations
        // de OficinaAuto (add_mecanica / erradica_locacao).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasTable('arquivos_audit_log')) {
            return;
        }

        // Idempotente: re-rodar com o mesmo alvo é no-op para o MySQL.
        DB::statement(
            'ALTER TABLE arquivos_audit_log MODIFY COLUMN action ENUM(' . self::ENUM_WIDE . ') NOT NULL'
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        if (! Schema::hasTable('arquivos_audit_log')) {
            return;
        }

        // Guarda append-only: estreitar o enum com linhas consumed gravadas
        // truncaria/invalidaria audit real (LGPD). Recusa explícita > perda silenciosa.
        $consumed = DB::table('arquivos_audit_log')
            ->where('action', 'signed_url_consumed')
            ->count();

        if ($consumed > 0) {
            throw new \RuntimeException(
                "Reversão bloqueada: {$consumed} linha(s) 'signed_url_consumed' em arquivos_audit_log. "
                . 'Estreitar o enum orfanaria audit append-only (ADR 0123 §8).'
            );
        }

        DB::statement(
            'ALTER TABLE arquivos_audit_log MODIFY COLUMN action ENUM(' . self::ENUM_NARROW . ') NOT NULL'
        );
    }
};
